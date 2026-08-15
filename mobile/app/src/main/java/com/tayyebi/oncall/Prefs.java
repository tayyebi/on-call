package com.tayyebi.oncall;

import android.content.Context;
import android.content.SharedPreferences;

/** Thin wrapper around the app's single SharedPreferences file. */
public final class Prefs {

    private static final String FILE = "on-call";

    private static final String KEY_SERVER = "server";
    private static final String KEY_UID = "uid";
    private static final String KEY_TOKEN = "token";
    private static final String KEY_CONNECTED_SINCE = "connected_since";
    private static final String KEY_LAST_SUCCESS = "last_success";
    private static final String KEY_UNREACHABLE_NOTIFIED = "unreachable_notified";

    private final SharedPreferences prefs;

    public Prefs(Context context) {
        prefs = context.getApplicationContext().getSharedPreferences(FILE, Context.MODE_PRIVATE);
    }

    public String getServer() {
        return prefs.getString(KEY_SERVER, "");
    }

    public void setServer(String server) {
        prefs.edit().putString(KEY_SERVER, server).apply();
    }

    public String getUid() {
        return prefs.getString(KEY_UID, "");
    }

    public String getToken() {
        return prefs.getString(KEY_TOKEN, "");
    }

    public boolean isPaired() {
        return !getUid().isEmpty() && !getToken().isEmpty();
    }

    public void savePairing(String server, String uid, String token) {
        prefs.edit()
                .putString(KEY_SERVER, server)
                .putString(KEY_UID, uid)
                .putString(KEY_TOKEN, token)
                .putLong(KEY_CONNECTED_SINCE, System.currentTimeMillis())
                .putLong(KEY_LAST_SUCCESS, System.currentTimeMillis())
                .putBoolean(KEY_UNREACHABLE_NOTIFIED, false)
                .apply();
    }

    public void clear() {
        prefs.edit()
                .remove(KEY_UID)
                .remove(KEY_TOKEN)
                .remove(KEY_CONNECTED_SINCE)
                .remove(KEY_LAST_SUCCESS)
                .remove(KEY_UNREACHABLE_NOTIFIED)
                .apply();
    }

    public long getConnectedSince() {
        return prefs.getLong(KEY_CONNECTED_SINCE, 0L);
    }

    public long getLastSuccess() {
        return prefs.getLong(KEY_LAST_SUCCESS, 0L);
    }

    public void markSuccess() {
        prefs.edit()
                .putLong(KEY_LAST_SUCCESS, System.currentTimeMillis())
                .putBoolean(KEY_UNREACHABLE_NOTIFIED, false)
                .apply();
    }

    public boolean isUnreachableNotified() {
        return prefs.getBoolean(KEY_UNREACHABLE_NOTIFIED, false);
    }

    public void setUnreachableNotified(boolean notified) {
        prefs.edit().putBoolean(KEY_UNREACHABLE_NOTIFIED, notified).apply();
    }
}
