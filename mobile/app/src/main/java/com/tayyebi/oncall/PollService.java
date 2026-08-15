package com.tayyebi.oncall;

import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.Service;
import android.content.Intent;
import android.media.Ringtone;
import android.media.RingtoneManager;
import android.net.Uri;
import android.os.Build;
import android.os.IBinder;
import android.telephony.SmsManager;
import android.util.Log;

import androidx.core.app.NotificationCompat;

import org.json.JSONArray;
import org.json.JSONObject;

/**
 * Foreground service that keeps a long-poll connection open against
 * /poll.php, executes whatever command comes back, and reports the outcome.
 *
 * If the server can't be reached for more than 5 minutes, a persistent
 * notification is raised so the user knows the device has gone dark.
 */
public class PollService extends Service {

    private static final String TAG = "PollService";
    private static final String CHANNEL_STATUS = "oncall-status";
    private static final String CHANNEL_ALERT = "oncall-unreachable";
    private static final int NOTIF_FOREGROUND = 1;
    private static final int NOTIF_UNREACHABLE = 2;
    private static final long UNREACHABLE_THRESHOLD_MS = 5 * 60 * 1000L;

    private volatile boolean running = false;
    private Thread loopThread;

    @Override
    public void onCreate() {
        super.onCreate();
        createChannels();
    }

    @Override
    public int onStartCommand(Intent intent, int flags, int startId) {
        startForeground(NOTIF_FOREGROUND, buildForegroundNotification());
        if (!running) {
            running = true;
            loopThread = new Thread(this::loop, "poll-loop");
            loopThread.start();
        }
        return START_STICKY;
    }

    @Override
    public void onDestroy() {
        running = false;
        if (loopThread != null) {
            loopThread.interrupt();
        }
        super.onDestroy();
    }

    @Override
    public IBinder onBind(Intent intent) {
        return null;
    }

    private void loop() {
        Prefs prefs = new Prefs(this);
        while (running && prefs.isPaired()) {
            try {
                JSONObject response = ApiClient.poll(prefs.getServer(), prefs.getUid(), prefs.getToken());
                prefs.markSuccess();
                dismissUnreachable();
                JSONArray commands = response.optJSONArray("commands");
                if (commands != null) {
                    for (int i = 0; i < commands.length(); i++) {
                        execute(prefs, commands.getJSONObject(i));
                    }
                }
            } catch (Exception e) {
                Log.w(TAG, "poll failed: " + e.getMessage());
                checkUnreachable(prefs);
                sleepQuietly(5000);
            }
        }
    }

    private void execute(Prefs prefs, JSONObject command) {
        int targetId = command.optInt("target_id");
        String type = command.optString("type");
        JSONObject payload = command.optJSONObject("payload");
        if (payload == null) {
            payload = new JSONObject();
        }
        String status = "success";
        String result = "";
        try {
            switch (type) {
                case "sms":
                    sendSms(payload.optString("number"), payload.optString("text"));
                    break;
                case "notification":
                    showRemoteNotification(payload.optString("text"));
                    break;
                case "ring":
                    playRing();
                    break;
                default:
                    status = "failed";
                    result = "unknown command type: " + type;
            }
        } catch (Exception e) {
            status = "failed";
            result = e.getMessage() != null ? e.getMessage() : e.getClass().getSimpleName();
        }

        try {
            ApiClient.report(prefs.getServer(), prefs.getUid(), prefs.getToken(), targetId, status, result);
        } catch (Exception e) {
            Log.w(TAG, "report failed: " + e.getMessage());
        }
    }

    private void sendSms(String number, String text) {
        SmsManager smsManager = SmsManager.getDefault();
        smsManager.sendTextMessage(number, null, text, null, null);
    }

    private void showRemoteNotification(String text) {
        NotificationCompat.Builder builder = new NotificationCompat.Builder(this, CHANNEL_STATUS)
                .setSmallIcon(android.R.drawable.ic_dialog_info)
                .setContentTitle("on-call")
                .setContentText(text)
                .setAutoCancel(true);
        NotificationManager manager = getSystemService(NotificationManager.class);
        manager.notify((int) System.currentTimeMillis(), builder.build());
    }

    private void playRing() {
        Uri ringUri = RingtoneManager.getDefaultUri(RingtoneManager.TYPE_RINGTONE);
        Ringtone ringtone = RingtoneManager.getRingtone(this, ringUri);
        if (ringtone != null) {
            ringtone.play();
        }
    }

    private void checkUnreachable(Prefs prefs) {
        long since = prefs.getLastSuccess();
        if (since == 0) {
            return;
        }
        boolean unreachable = System.currentTimeMillis() - since > UNREACHABLE_THRESHOLD_MS;
        if (unreachable && !prefs.isUnreachableNotified()) {
            prefs.setUnreachableNotified(true);
            NotificationCompat.Builder builder = new NotificationCompat.Builder(this, CHANNEL_ALERT)
                    .setSmallIcon(android.R.drawable.stat_notify_error)
                    .setContentTitle("on-call: server unreachable")
                    .setContentText("Could not reach the configured server for over 5 minutes.")
                    .setOngoing(true)
                    .setPriority(NotificationCompat.PRIORITY_HIGH);
            NotificationManager manager = getSystemService(NotificationManager.class);
            manager.notify(NOTIF_UNREACHABLE, builder.build());
        }
    }

    private void dismissUnreachable() {
        NotificationManager manager = getSystemService(NotificationManager.class);
        manager.cancel(NOTIF_UNREACHABLE);
    }

    private Notification buildForegroundNotification() {
        return new NotificationCompat.Builder(this, CHANNEL_STATUS)
                .setSmallIcon(android.R.drawable.stat_sys_download_done)
                .setContentTitle("on-call")
                .setContentText("Connected — listening for commands")
                .setOngoing(true)
                .build();
    }

    private void createChannels() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) {
            return;
        }
        NotificationManager manager = getSystemService(NotificationManager.class);
        manager.createNotificationChannel(new NotificationChannel(
                CHANNEL_STATUS, "Status", NotificationManager.IMPORTANCE_LOW));
        manager.createNotificationChannel(new NotificationChannel(
                CHANNEL_ALERT, "Unreachable server", NotificationManager.IMPORTANCE_HIGH));
    }

    private void sleepQuietly(long ms) {
        try {
            Thread.sleep(ms);
        } catch (InterruptedException e) {
            Thread.currentThread().interrupt();
        }
    }
}
