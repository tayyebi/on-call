package com.tayyebi.oncall;

import org.json.JSONException;
import org.json.JSONObject;

import java.io.ByteArrayOutputStream;
import java.io.IOException;
import java.io.InputStream;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.nio.charset.StandardCharsets;

/** Plain HttpURLConnection client for the on-call server (no third-party HTTP libs). */
public final class ApiClient {

    private ApiClient() {
    }

    public static JSONObject pair(String server, String code, String model) throws IOException, JSONException {
        JSONObject body = new JSONObject();
        try {
            body.put("code", code);
            body.put("model", model);
        } catch (JSONException e) {
            throw new IllegalStateException(e);
        }
        return post(server, "/pair.php", body, 15000);
    }

    /** Blocks up to ~30s waiting for the server's long-poll to return. */
    public static JSONObject poll(String server, String uid, String token) throws IOException, JSONException {
        String url = normalize(server) + "/poll.php?uid=" + urlEncode(uid) + "&token=" + urlEncode(token);
        HttpURLConnection conn = (HttpURLConnection) new URL(url).openConnection();
        conn.setRequestMethod("GET");
        conn.setConnectTimeout(10000);
        conn.setReadTimeout(30000);
        try {
            return readJson(conn);
        } finally {
            conn.disconnect();
        }
    }

    public static void report(String server, String uid, String token, int targetId, String status, String result)
            throws IOException, JSONException {
        JSONObject body = new JSONObject();
        body.put("uid", uid);
        body.put("token", token);
        body.put("target_id", targetId);
        body.put("status", status);
        body.put("result", result);
        post(server, "/report.php", body, 15000);
    }

    private static JSONObject post(String server, String path, JSONObject body, int timeoutMs)
            throws IOException, JSONException {
        HttpURLConnection conn = (HttpURLConnection) new URL(normalize(server) + path).openConnection();
        conn.setRequestMethod("POST");
        conn.setDoOutput(true);
        conn.setConnectTimeout(timeoutMs);
        conn.setReadTimeout(timeoutMs);
        conn.setRequestProperty("Content-Type", "application/json");
        try (OutputStream os = conn.getOutputStream()) {
            os.write(body.toString().getBytes(StandardCharsets.UTF_8));
        }
        try {
            return readJson(conn);
        } finally {
            conn.disconnect();
        }
    }

    private static JSONObject readJson(HttpURLConnection conn) throws IOException, JSONException {
        int code = conn.getResponseCode();
        InputStream stream = code >= 200 && code < 300 ? conn.getInputStream() : conn.getErrorStream();
        String text = readAll(stream);
        JSONObject json = new JSONObject(text.isEmpty() ? "{}" : text);
        if (code < 200 || code >= 300) {
            throw new IOException("HTTP " + code + ": " + json.optString("error", text));
        }
        return json;
    }

    private static String readAll(InputStream in) throws IOException {
        if (in == null) {
            return "";
        }
        ByteArrayOutputStream buffer = new ByteArrayOutputStream();
        byte[] chunk = new byte[4096];
        int read;
        while ((read = in.read(chunk)) != -1) {
            buffer.write(chunk, 0, read);
        }
        return buffer.toString("UTF-8");
    }

    private static String normalize(String server) {
        String s = server.trim();
        if (!s.startsWith("http://") && !s.startsWith("https://")) {
            s = "http://" + s;
        }
        while (s.endsWith("/")) {
            s = s.substring(0, s.length() - 1);
        }
        return s;
    }

    private static String urlEncode(String value) {
        try {
            return java.net.URLEncoder.encode(value, "UTF-8");
        } catch (Exception e) {
            return value;
        }
    }
}
