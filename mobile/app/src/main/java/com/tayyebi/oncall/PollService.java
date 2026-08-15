package com.tayyebi.oncall;

import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.app.Service;
import android.content.Intent;
import android.media.Ringtone;
import android.media.RingtoneManager;
import android.net.Uri;
import android.os.Build;
import android.os.IBinder;
import android.telephony.SmsManager;
import android.util.Log;

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
    private static final String CHANNEL_RING = "oncall-ring";
    private static final String CHANNEL_COMMAND = "oncall-command";
    private static final int NOTIF_FOREGROUND = 1;
    private static final int NOTIF_UNREACHABLE = 2;
    private static final int NOTIF_RING = 3;
    private static final long UNREACHABLE_THRESHOLD_MS = 5 * 60 * 1000L;
    private static final String ACTION_STOP_RING = "com.tayyebi.oncall.STOP_RING";
    private static final String ACTION_STOP = "com.tayyebi.oncall.STOP";

    private volatile boolean running = false;
    private Thread loopThread;
    private Ringtone activeRingtone;

    @Override
    public void onCreate() {
        super.onCreate();
        createChannels();
    }

    @Override
    public int onStartCommand(Intent intent, int flags, int startId) {
        if (intent != null && ACTION_STOP.equals(intent.getAction())) {
            running = false;
            stopRing();
            dismissUnreachable();
            stopForeground(true);
            stopSelf();
            return START_NOT_STICKY;
        }
        if (intent != null && ACTION_STOP_RING.equals(intent.getAction())) {
            stopRing();
            return START_STICKY;
        }
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
        stopRing();
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
                JSONObject response = ApiClient.poll(prefs.getServer(), prefs.getUid(), prefs.getToken(),
                        prefs.allowsSelfSignedCertificate(prefs.getServer()));
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
        String detail = "";
        try {
            switch (type) {
                case "sms":
                    String number = payload.optString("number");
                    String text = payload.optString("text");
                    sendSms(number, text);
                    detail = "SMS to " + number;
                    break;
                case "notification":
                    String notifText = payload.optString("text");
                    showRemoteNotification(notifText);
                    detail = notifText;
                    break;
                case "ring":
                    playRing();
                    detail = "Ring";
                    break;
                default:
                    status = "failed";
                    result = "unknown command type: " + type;
                    detail = type;
            }
        } catch (Exception e) {
            status = "failed";
            result = e.getMessage() != null ? e.getMessage() : e.getClass().getSimpleName();
            detail = type;
        }

        prefs.logCommand(type, detail, status, result);

        try {
            ApiClient.report(prefs.getServer(), prefs.getUid(), prefs.getToken(), targetId, status, result,
                    prefs.allowsSelfSignedCertificate(prefs.getServer()));
        } catch (Exception e) {
            Log.w(TAG, "report failed: " + e.getMessage());
        }
    }

    private void sendSms(String number, String text) {
        SmsManager smsManager = SmsManager.getDefault();
        smsManager.sendTextMessage(number, null, text, null, null);
    }

    private void showRemoteNotification(String text) {
        Notification.Builder builder = newBuilder(CHANNEL_COMMAND)
                .setSmallIcon(android.R.drawable.ic_dialog_info)
                .setContentTitle("on-call")
                .setContentText(text)
                .setStyle(new Notification.BigTextStyle().bigText(text))
                .setAutoCancel(true)
                .setPriority(Notification.PRIORITY_HIGH);
        NotificationManager manager = getSystemService(NotificationManager.class);
        manager.notify((int) System.currentTimeMillis(), builder.build());
    }

    private void playRing() {
        stopRing();
        Uri ringUri = RingtoneManager.getDefaultUri(RingtoneManager.TYPE_RINGTONE);
        Ringtone ringtone = RingtoneManager.getRingtone(this, ringUri);
        if (ringtone == null) {
            throw new IllegalStateException("No ringtone is configured");
        }
        activeRingtone = ringtone;
        ringtone.play();

        Intent stopIntent = new Intent(this, PollService.class);
        stopIntent.setAction(ACTION_STOP_RING);
        int flags = PendingIntent.FLAG_UPDATE_CURRENT;
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            flags |= PendingIntent.FLAG_IMMUTABLE;
        }
        PendingIntent stopPending = PendingIntent.getService(this, 0, stopIntent, flags);

        Notification.Builder builder = newBuilder(CHANNEL_RING)
                .setSmallIcon(android.R.drawable.ic_lock_idle_lock)
                .setContentTitle("on-call")
                .setContentText("Remote ring active")
                .setOngoing(true)
                .addAction(android.R.drawable.ic_media_pause, "Stop", stopPending);

        NotificationManager manager = getSystemService(NotificationManager.class);
        manager.notify(NOTIF_RING, builder.build());
    }

    private void stopRing() {
        if (activeRingtone != null) {
            try {
                activeRingtone.stop();
            } catch (Exception ignored) {
            }
            activeRingtone = null;
        }
        NotificationManager manager = getSystemService(NotificationManager.class);
        manager.cancel(NOTIF_RING);
    }

    private void checkUnreachable(Prefs prefs) {
        long since = prefs.getLastSuccess();
        if (since == 0) {
            return;
        }
        boolean unreachable = System.currentTimeMillis() - since > UNREACHABLE_THRESHOLD_MS;
        if (unreachable && !prefs.isUnreachableNotified()) {
            prefs.setUnreachableNotified(true);
            Notification.Builder builder = newBuilder(CHANNEL_ALERT)
                    .setSmallIcon(android.R.drawable.stat_notify_error)
                    .setContentTitle("on-call: server unreachable")
                    .setContentText("Could not reach the configured server for over 5 minutes.")
                    .setOngoing(true)
                    .setPriority(Notification.PRIORITY_HIGH);
            NotificationManager manager = getSystemService(NotificationManager.class);
            manager.notify(NOTIF_UNREACHABLE, builder.build());
        }
    }

    private void dismissUnreachable() {
        NotificationManager manager = getSystemService(NotificationManager.class);
        manager.cancel(NOTIF_UNREACHABLE);
    }

    private Notification buildForegroundNotification() {
        Intent exitIntent = new Intent(this, PollService.class);
        exitIntent.setAction(ACTION_STOP);
        int flags = PendingIntent.FLAG_UPDATE_CURRENT;
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            flags |= PendingIntent.FLAG_IMMUTABLE;
        }
        PendingIntent exitPending = PendingIntent.getService(this, 1, exitIntent, flags);

        return newBuilder(CHANNEL_STATUS)
                .setSmallIcon(android.R.drawable.stat_sys_download_done)
                .setContentTitle("on-call")
                .setContentText("Connected - listening for commands")
                .setOngoing(true)
                .addAction(android.R.drawable.ic_menu_close_clear_cancel, "Stop", exitPending)
                .build();
    }

    @SuppressWarnings("deprecation")
    private Notification.Builder newBuilder(String channelId) {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            return new Notification.Builder(this, channelId);
        }
        return new Notification.Builder(this);
    }

    private void createChannels() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) {
            return;
        }
        NotificationManager manager = getSystemService(NotificationManager.class);
        manager.createNotificationChannel(new NotificationChannel(
                CHANNEL_STATUS, "Status", NotificationManager.IMPORTANCE_LOW));
        manager.createNotificationChannel(new NotificationChannel(
                CHANNEL_COMMAND, "Commands", NotificationManager.IMPORTANCE_HIGH));
        manager.createNotificationChannel(new NotificationChannel(
                CHANNEL_ALERT, "Unreachable server", NotificationManager.IMPORTANCE_HIGH));
        manager.createNotificationChannel(new NotificationChannel(
                CHANNEL_RING, "Remote ring", NotificationManager.IMPORTANCE_HIGH));
    }

    private void sleepQuietly(long ms) {
        try {
            Thread.sleep(ms);
        } catch (InterruptedException e) {
            Thread.currentThread().interrupt();
        }
    }
}
