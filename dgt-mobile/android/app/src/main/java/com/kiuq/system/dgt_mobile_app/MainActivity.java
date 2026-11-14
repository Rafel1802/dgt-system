package com.kiuq.system.dgt_mobile_app;

import android.Manifest;
import android.content.Context;
import android.content.pm.PackageManager;
import android.media.AudioDeviceInfo;
import android.media.AudioManager;
import android.net.ConnectivityManager;
import android.net.Network;
import android.net.NetworkCapabilities;
import android.os.Build;

import androidx.annotation.NonNull;
import androidx.core.app.ActivityCompat;
import androidx.core.content.ContextCompat;

import java.util.HashMap;
import java.util.Map;

import io.flutter.embedding.android.FlutterActivity;
import io.flutter.embedding.engine.FlutterEngine;
import io.flutter.plugin.common.MethodCall;
import io.flutter.plugin.common.MethodChannel;

public class MainActivity extends FlutterActivity {
    private static final String CHANNEL = "kiuq_system/ios_bridge";
    private static final int PERMISSION_REQ_MEDIA = 4102;
    private MethodChannel.Result pendingPermissionResult;

    @Override
    public void configureFlutterEngine(@NonNull FlutterEngine flutterEngine) {
        super.configureFlutterEngine(flutterEngine);
        new MethodChannel(flutterEngine.getDartExecutor().getBinaryMessenger(), CHANNEL)
            .setMethodCallHandler(this::handleBridgeMethod);
    }

    private void handleBridgeMethod(@NonNull MethodCall call, @NonNull MethodChannel.Result result) {
        switch (call.method) {
            case "checkConnectivity":
                result.success(checkAndroidConnectivity());
                break;
            case "requestMediaPermission": {
                String kind = call.argument("kind");
                String perm = "microphone".equals(kind)
                    ? Manifest.permission.RECORD_AUDIO
                    : Manifest.permission.CAMERA;
                if (ContextCompat.checkSelfPermission(this, perm) == PackageManager.PERMISSION_GRANTED) {
                    result.success(true);
                } else {
                    pendingPermissionResult = result;
                    ActivityCompat.requestPermissions(this, new String[]{perm}, PERMISSION_REQ_MEDIA);
                }
                break;
            }
            case "configureCallAudio": {
                Boolean speakerArg = call.argument("speakerOn");
                boolean speakerOn = speakerArg != null ? speakerArg : false;
                configureAndroidCallAudio(speakerOn);
                result.success(true);
                break;
            }
            case "setSpeakerphone": {
                Boolean enabledArg = call.argument("enabled");
                boolean enabled = enabledArg == null || enabledArg;
                setAndroidSpeakerphone(enabled);
                result.success(true);
                break;
            }
            case "deactivateCallAudio":
                deactivateAndroidCallAudio();
                result.success(true);
                break;
            default:
                result.success(false);
                break;
        }
    }

    private Map<String, Object> checkAndroidConnectivity() {
        Map<String, Object> map = new HashMap<>();
        ConnectivityManager cm = (ConnectivityManager) getSystemService(Context.CONNECTIVITY_SERVICE);
        if (cm == null) {
            map.put("online", true);
            map.put("type", "wifi");
            return map;
        }
        Network active = cm.getActiveNetwork();
        NetworkCapabilities caps = active != null ? cm.getNetworkCapabilities(active) : null;
        boolean online = caps != null && caps.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET);
        String type = "none";
        if (caps != null) {
            if (caps.hasTransport(NetworkCapabilities.TRANSPORT_WIFI)) {
                type = "wifi";
            } else if (caps.hasTransport(NetworkCapabilities.TRANSPORT_CELLULAR)) {
                type = "cellular";
            } else if (online) {
                type = "other";
            }
        }
        map.put("online", online);
        map.put("type", type);
        return map;
    }

    private void configureAndroidCallAudio(boolean speakerOn) {
        AudioManager am = (AudioManager) getSystemService(Context.AUDIO_SERVICE);
        if (am == null) return;
        am.setMode(AudioManager.MODE_IN_COMMUNICATION);
        setAndroidSpeakerphone(speakerOn);
    }

    @SuppressWarnings("deprecation")
    private void setAndroidSpeakerphone(boolean enabled) {
        AudioManager am = (AudioManager) getSystemService(Context.AUDIO_SERVICE);
        if (am == null) return;
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            if (!enabled) {
                am.clearCommunicationDevice();
                for (AudioDeviceInfo dev : am.getAvailableCommunicationDevices()) {
                    if (dev.getType() == AudioDeviceInfo.TYPE_BLUETOOTH_SCO ||
                        dev.getType() == AudioDeviceInfo.TYPE_BLE_HEADSET ||
                        dev.getType() == AudioDeviceInfo.TYPE_BUILTIN_EARPIECE) {
                        am.setCommunicationDevice(dev);
                        break;
                    }
                }
            } else {
                for (AudioDeviceInfo dev : am.getAvailableCommunicationDevices()) {
                    if (dev.getType() == AudioDeviceInfo.TYPE_BUILTIN_SPEAKER) {
                        am.setCommunicationDevice(dev);
                        break;
                    }
                }
            }
        } else {
            am.setSpeakerphoneOn(enabled);
        }
    }

    @SuppressWarnings("deprecation")
    private void deactivateAndroidCallAudio() {
        AudioManager am = (AudioManager) getSystemService(Context.AUDIO_SERVICE);
        if (am == null) return;
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
            am.clearCommunicationDevice();
        } else {
            am.setSpeakerphoneOn(false);
        }
        am.setMode(AudioManager.MODE_NORMAL);
    }

    @Override
    public void onRequestPermissionsResult(int requestCode, @NonNull String[] permissions, @NonNull int[] grantResults) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults);
        if (requestCode == PERMISSION_REQ_MEDIA && pendingPermissionResult != null) {
            boolean granted = grantResults.length > 0 && grantResults[0] == PackageManager.PERMISSION_GRANTED;
            pendingPermissionResult.success(granted);
            pendingPermissionResult = null;
        }
    }
}
