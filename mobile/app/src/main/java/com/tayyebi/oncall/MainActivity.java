package com.tayyebi.oncall;

import android.Manifest;
import android.app.Activity;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.os.Build;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.widget.Button;
import android.widget.EditText;
import android.widget.LinearLayout;
import android.widget.TextView;
import android.widget.Toast;

import androidx.core.app.ActivityCompat;

import java.text.DateFormat;
import java.util.Date;

/**
 * Three states, per mobile/MainActivity.txt:
 *  - disconnected: ip + pair-code form, connect button.
 *  - paired but server unreachable: same editable form, plus a retry button.
 *  - paired and healthy: "connected since" + disconnect button.
 */
public class MainActivity extends Activity {

    private static final long UNREACHABLE_THRESHOLD_MS = 5 * 60 * 1000L;
    private static final long UI_REFRESH_MS = 15_000L;

    private Prefs prefs;
    private final Handler handler = new Handler(Looper.getMainLooper());

    private TextView statusText;
    private LinearLayout connectForm;
    private EditText serverIpInput;
    private EditText pairCodeInput;
    private Button connectButton;
    private Button retryButton;
    private LinearLayout connectedPanel;
    private Button connectedSinceButton;
    private Button disconnectButton;

    private final Runnable refreshRunnable = new Runnable() {
        @Override
        public void run() {
            renderState();
            handler.postDelayed(this, UI_REFRESH_MS);
        }
    };

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);
        prefs = new Prefs(this);

        statusText = findViewById(R.id.statusText);
        connectForm = findViewById(R.id.connectForm);
        serverIpInput = findViewById(R.id.serverIpInput);
        pairCodeInput = findViewById(R.id.pairCodeInput);
        connectButton = findViewById(R.id.connectButton);
        retryButton = findViewById(R.id.retryButton);
        connectedPanel = findViewById(R.id.connectedPanel);
        connectedSinceButton = findViewById(R.id.connectedSinceButton);
        disconnectButton = findViewById(R.id.disconnectButton);

        serverIpInput.setText(prefs.getServer());

        connectButton.setOnClickListener(v -> connect());
        retryButton.setOnClickListener(v -> retry());
        disconnectButton.setOnClickListener(v -> disconnect());

        requestRuntimePermissions();
    }

    @Override
    protected void onResume() {
        super.onResume();
        handler.post(refreshRunnable);
    }

    @Override
    protected void onPause() {
        super.onPause();
        handler.removeCallbacks(refreshRunnable);
    }

    private void requestRuntimePermissions() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            ActivityCompat.requestPermissions(this,
                    new String[]{Manifest.permission.POST_NOTIFICATIONS, Manifest.permission.SEND_SMS}, 1);
        } else {
            ActivityCompat.requestPermissions(this, new String[]{Manifest.permission.SEND_SMS}, 1);
        }
    }

    /** disconnected / unreachable / healthy — decided from prefs, not live network state. */
    private enum State { DISCONNECTED, UNREACHABLE, HEALTHY }

    private State currentState() {
        if (!prefs.isPaired()) {
            return State.DISCONNECTED;
        }
        long since = prefs.getLastSuccess();
        boolean stale = since == 0 || (System.currentTimeMillis() - since) > UNREACHABLE_THRESHOLD_MS;
        return stale ? State.UNREACHABLE : State.HEALTHY;
    }

    private void renderState() {
        switch (currentState()) {
            case DISCONNECTED:
                statusText.setText("Not connected");
                connectForm.setVisibility(LinearLayout.VISIBLE);
                retryButton.setVisibility(android.view.View.GONE);
                connectButton.setVisibility(android.view.View.VISIBLE);
                connectedPanel.setVisibility(LinearLayout.GONE);
                break;
            case UNREACHABLE:
                statusText.setText("Paired, but the server is unreachable");
                connectForm.setVisibility(LinearLayout.VISIBLE);
                retryButton.setVisibility(android.view.View.VISIBLE);
                connectButton.setVisibility(android.view.View.VISIBLE);
                connectedPanel.setVisibility(LinearLayout.GONE);
                break;
            case HEALTHY:
                statusText.setText("Connected");
                connectForm.setVisibility(LinearLayout.GONE);
                connectedPanel.setVisibility(LinearLayout.VISIBLE);
                String since = DateFormat.getDateTimeInstance().format(new Date(prefs.getConnectedSince()));
                connectedSinceButton.setText("Connected since " + since);
                break;
        }
    }

    private void connect() {
        String server = serverIpInput.getText().toString().trim();
        String code = pairCodeInput.getText().toString().trim();
        if (server.isEmpty() || code.isEmpty()) {
            Toast.makeText(this, "Enter the server address and the pair code", Toast.LENGTH_SHORT).show();
            return;
        }
        connectButton.setEnabled(false);
        new Thread(() -> {
            try {
                org.json.JSONObject result = ApiClient.pair(server, code, Build.MODEL);
                String uid = result.getString("uid");
                String token = result.getString("token");
                prefs.savePairing(server, uid, token);
                startPolling();
                runOnUiThread(() -> {
                    connectButton.setEnabled(true);
                    renderState();
                });
            } catch (Exception e) {
                runOnUiThread(() -> {
                    connectButton.setEnabled(true);
                    Toast.makeText(this, "Could not pair: " + e.getMessage(), Toast.LENGTH_LONG).show();
                });
            }
        }).start();
    }

    /** Retry: re-pair if a fresh code was entered, otherwise just refresh the connection. */
    private void retry() {
        String code = pairCodeInput.getText().toString().trim();
        if (!code.isEmpty()) {
            connect();
            return;
        }
        String server = serverIpInput.getText().toString().trim();
        if (!server.isEmpty()) {
            prefs.setServer(server);
        }
        startPolling();
        Toast.makeText(this, "Retrying connection...", Toast.LENGTH_SHORT).show();
        renderState();
    }

    private void disconnect() {
        stopService(new Intent(this, PollService.class));
        prefs.clear();
        pairCodeInput.setText("");
        renderState();
    }

    private void startPolling() {
        Intent intent = new Intent(this, PollService.class);
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            startForegroundService(intent);
        } else {
            startService(intent);
        }
    }
}
