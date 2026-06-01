/**
 * Prosperous Motos — Network Status Monitor
 * Detects online/offline state and triggers sync on reconnection.
 */

class NetworkStatus {
    constructor(syncManager) {
        this.syncManager = syncManager;
        this._isOnline = navigator.onLine;
        this.listeners = [];
        this._pingInterval = null;
        this._started = false;
    }

    get isOnline() {
        return this._isOnline;
    }

    onStatusChange(callback) {
        this.listeners.push(callback);
    }

    _notify(online) {
        if (this._isOnline !== online) {
            this._isOnline = online;
            this.listeners.forEach((cb) => cb(online));

            // Auto-sync when coming back online
            if (online && this.syncManager) {
                this.syncManager.fullSync();
            }
        }
    }

    startMonitoring() {
        if (this._started) return;
        this._started = true;

        window.addEventListener('online', () => this._notify(true));
        window.addEventListener('offline', () => this._notify(false));

        // Periodic check: only used to detect when we come BACK online
        // (some browsers don't fire the 'online' event reliably)
        // Never marks user as offline — only browser events do that.
        this._pingInterval = setInterval(async () => {
            if (!this._isOnline && this.syncManager) {
                // We think we're offline — check if we're actually back online
                try {
                    const online = await this.syncManager.checkConnectivity();
                    if (online) {
                        this._notify(true);
                    }
                } catch {
                    // Still offline, do nothing
                }
            }
        }, 30000);
    }

    stopMonitoring() {
        if (this._pingInterval) {
            clearInterval(this._pingInterval);
            this._pingInterval = null;
        }
        this._started = false;
    }
}

export { NetworkStatus };
