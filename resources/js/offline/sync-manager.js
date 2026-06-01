/**
 * Prosperous Motos — Sync Manager
 * Orchestrates data synchronization between IndexedDB and the server.
 */

class SyncManager {
    constructor(offlineDB) {
        this.offlineDB = offlineDB;
        this.isSyncing = false;
        this.listeners = {
            syncStart: [],
            syncComplete: [],
            syncError: [],
            conflict: [],
            pendingCountChange: [],
        };
    }

    on(event, callback) {
        if (this.listeners[event]) {
            this.listeners[event].push(callback);
        }
    }

    _emit(event, data) {
        (this.listeners[event] || []).forEach((cb) => cb(data));
    }

    /**
     * Get the CSRF token from the meta tag.
     */
    _getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : '';
    }

    /**
     * Make an authenticated API request.
     */
    async _apiRequest(url, options = {}) {
        const defaults = {
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': this._getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        };

        const response = await fetch(url, { ...defaults, ...options });

        if (!response.ok) {
            throw new Error(`API error: ${response.status} ${response.statusText}`);
        }

        return response.json();
    }

    /**
     * Full sync: bootstrap or delta + push pending operations.
     */
    async fullSync() {
        if (this.isSyncing) return;

        this.isSyncing = true;
        this._emit('syncStart');

        try {
            // 1. Pull data from server (bootstrap or delta)
            const lastSync = await this.offlineDB.getLastSyncTimestamp();

            if (lastSync) {
                await this.performDeltaSync(lastSync);
            } else {
                await this.performBootstrapSync();
            }

            // 2. Push pending operations
            await this.pushPendingOperations();

            this._emit('syncComplete', { success: true });
        } catch (error) {
            console.error('Sync error:', error);
            this._emit('syncError', { error: error.message });
        } finally {
            this.isSyncing = false;
        }
    }

    /**
     * Bootstrap sync — full data load from server.
     */
    async performBootstrapSync() {
        const result = await this._apiRequest('/api/sync/bootstrap');

        if (result.success && result.data) {
            await this.offlineDB.syncBootstrap(result.data);
            await this.offlineDB.setLastSyncTimestamp(result.server_time);
        }
    }

    /**
     * Delta sync — incremental data from server.
     */
    async performDeltaSync(since) {
        const result = await this._apiRequest(`/api/sync/delta?since=${encodeURIComponent(since)}`);

        if (result.success && result.data) {
            await this.offlineDB.syncDelta(result.data);
            await this.offlineDB.setLastSyncTimestamp(result.server_time);
        }
    }

    /**
     * Push all pending offline operations to the server.
     */
    async pushPendingOperations() {
        const operations = await this.offlineDB.getAllPendingOperations();

        if (operations.length === 0) return;

        try {
            const result = await this._apiRequest('/api/sync/push', {
                method: 'POST',
                body: JSON.stringify({ operations }),
            });

            if (result.success && result.results) {
                for (const opResult of result.results) {
                    if (opResult.status === 'synced' || opResult.status === 'conflict') {
                        // Find the original operation to determine its type
                        const originalOp = operations.find(
                            (op) => op.client_uuid === opResult.client_uuid
                        );
                        if (originalOp) {
                            await this.offlineDB.removePending(originalOp.type, opResult.client_uuid);
                        }

                        if (opResult.status === 'conflict') {
                            this._emit('conflict', opResult);
                        }
                    }
                    // 'error' status: keep in queue for retry
                }

                // Notify about pending count change
                const newCount = await this.offlineDB.getPendingCount();
                this._emit('pendingCountChange', { count: newCount });
            }
        } catch (error) {
            // Network error — operations stay in queue
            console.warn('Push failed, operations kept in queue:', error.message);
        }
    }

    /**
     * Quick connectivity check.
     */
    async checkConnectivity() {
        try {
            const result = await this._apiRequest('/api/ping');
            return result.online === true;
        } catch {
            return false;
        }
    }
}

export { SyncManager };
