/**
 * Web Push 알림 유틸
 */
const WPPush = {
    _vapidKey: null,

    isSupported() {
        return 'PushManager' in window && 'Notification' in window && 'serviceWorker' in navigator;
    },

    async getVapidKey() {
        if (this._vapidKey) return this._vapidKey;
        try {
            const res = await fetch('/api/push/vapid');
            const data = await res.json();
            if (data.success) {
                this._vapidKey = data.data.publicKey;
                return this._vapidKey;
            }
        } catch (e) {
            // ignore
        }
        return null;
    },

    async subscribe(tripCode, userId, csrfToken) {
        if (!this.isSupported()) return false;

        const permission = await Notification.requestPermission();
        if (permission !== 'granted') return false;

        const vapidKey = await this.getVapidKey();
        if (!vapidKey) return false;

        const reg = await navigator.serviceWorker.ready;
        const sub = await reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: this._urlBase64ToUint8Array(vapidKey),
        });

        const key = sub.getKey('p256dh');
        const auth = sub.getKey('auth');

        const res = await fetch('/api/push/subscribe', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                csrf_token: csrfToken,
                trip_code: tripCode,
                user_id: userId,
                endpoint: sub.endpoint,
                p256dh: btoa(String.fromCharCode(...new Uint8Array(key))),
                auth: btoa(String.fromCharCode(...new Uint8Array(auth))),
            }),
        });

        const data = await res.json();
        return data.success;
    },

    async unsubscribe(csrfToken) {
        if (!this.isSupported()) return false;

        const reg = await navigator.serviceWorker.ready;
        const sub = await reg.pushManager.getSubscription();
        if (!sub) return true;

        await sub.unsubscribe();

        await fetch('/api/push/subscribe?csrf_token=' + encodeURIComponent(csrfToken) +
            '&endpoint=' + encodeURIComponent(sub.endpoint), {
            method: 'DELETE',
        });

        return true;
    },

    async isSubscribed() {
        if (!this.isSupported()) return false;
        try {
            const reg = await navigator.serviceWorker.ready;
            const sub = await reg.pushManager.getSubscription();
            return !!sub;
        } catch (e) {
            return false;
        }
    },

    getPermission() {
        if (!('Notification' in window)) return 'unsupported';
        return Notification.permission; // 'default', 'granted', 'denied'
    },

    _urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        const rawData = atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    },
};
