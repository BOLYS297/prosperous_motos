/**
 * Prosperous Motos — IndexedDB Offline Store
 * Manages local data storage for offline-first PWA.
 */

const DB_NAME = 'prosperous_motos_offline';
const DB_VERSION = 1;

const STORES = {
    PRODUITS: 'produits',
    STOCKS: 'stocks',
    GROSSISTES: 'grossistes',
    PRIX_GROSSISTES: 'prix_grossistes',
    BOUTIQUE: 'boutique',
    PENDING_VENTES: 'pending_ventes',
    PENDING_DEPENSES: 'pending_depenses',
    PENDING_TRANSFERTS: 'pending_transferts',
    VENTES_HISTORY: 'ventes_history',
    SYNC_META: 'sync_meta',
};

class OfflineDB {
    constructor() {
        this.db = null;
    }

    /**
     * Open or create the IndexedDB database.
     */
    async init() {
        if (this.db) return this.db;

        return new Promise((resolve, reject) => {
            const request = indexedDB.open(DB_NAME, DB_VERSION);

            request.onupgradeneeded = (event) => {
                const db = event.target.result;

                // Reference data stores
                if (!db.objectStoreNames.contains(STORES.PRODUITS)) {
                    db.createObjectStore(STORES.PRODUITS, { keyPath: 'id' });
                }
                if (!db.objectStoreNames.contains(STORES.STOCKS)) {
                    const stockStore = db.createObjectStore(STORES.STOCKS, { keyPath: ['boutique_id', 'produit_id'] });
                    stockStore.createIndex('by_produit', 'produit_id', { unique: false });
                }
                if (!db.objectStoreNames.contains(STORES.GROSSISTES)) {
                    db.createObjectStore(STORES.GROSSISTES, { keyPath: 'id' });
                }
                if (!db.objectStoreNames.contains(STORES.PRIX_GROSSISTES)) {
                    const prixStore = db.createObjectStore(STORES.PRIX_GROSSISTES, { keyPath: 'id' });
                    prixStore.createIndex('by_grossiste', 'grossiste_id', { unique: false });
                    prixStore.createIndex('by_produit', 'produit_id', { unique: false });
                }
                if (!db.objectStoreNames.contains(STORES.BOUTIQUE)) {
                    db.createObjectStore(STORES.BOUTIQUE, { keyPath: 'id' });
                }

                // Pending operations stores (offline queue)
                if (!db.objectStoreNames.contains(STORES.PENDING_VENTES)) {
                    const ventesStore = db.createObjectStore(STORES.PENDING_VENTES, { keyPath: 'client_uuid' });
                    ventesStore.createIndex('by_timestamp', 'timestamp', { unique: false });
                }
                if (!db.objectStoreNames.contains(STORES.PENDING_DEPENSES)) {
                    const depensesStore = db.createObjectStore(STORES.PENDING_DEPENSES, { keyPath: 'client_uuid' });
                    depensesStore.createIndex('by_timestamp', 'timestamp', { unique: false });
                }
                if (!db.objectStoreNames.contains(STORES.PENDING_TRANSFERTS)) {
                    const transfertsStore = db.createObjectStore(STORES.PENDING_TRANSFERTS, { keyPath: 'client_uuid' });
                    transfertsStore.createIndex('by_timestamp', 'timestamp', { unique: false });
                }

                // History cache
                if (!db.objectStoreNames.contains(STORES.VENTES_HISTORY)) {
                    db.createObjectStore(STORES.VENTES_HISTORY, { keyPath: 'id' });
                }

                // Sync metadata
                if (!db.objectStoreNames.contains(STORES.SYNC_META)) {
                    db.createObjectStore(STORES.SYNC_META, { keyPath: 'key' });
                }
            };

            request.onsuccess = (event) => {
                this.db = event.target.result;
                resolve(this.db);
            };

            request.onerror = (event) => {
                console.error('IndexedDB error:', event.target.error);
                reject(event.target.error);
            };
        });
    }

    // ─────────────────────────────────────────────
    // Generic CRUD helpers
    // ─────────────────────────────────────────────

    async _put(storeName, data) {
        const db = await this.init();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(storeName, 'readwrite');
            const store = tx.objectStore(storeName);
            store.put(data);
            tx.oncomplete = () => resolve();
            tx.onerror = (e) => reject(e.target.error);
        });
    }

    async _putMany(storeName, items) {
        const db = await this.init();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(storeName, 'readwrite');
            const store = tx.objectStore(storeName);
            items.forEach((item) => store.put(item));
            tx.oncomplete = () => resolve();
            tx.onerror = (e) => reject(e.target.error);
        });
    }

    async _getAll(storeName) {
        const db = await this.init();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(storeName, 'readonly');
            const store = tx.objectStore(storeName);
            const request = store.getAll();
            request.onsuccess = () => resolve(request.result);
            request.onerror = (e) => reject(e.target.error);
        });
    }

    async _get(storeName, key) {
        const db = await this.init();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(storeName, 'readonly');
            const store = tx.objectStore(storeName);
            const request = store.get(key);
            request.onsuccess = () => resolve(request.result);
            request.onerror = (e) => reject(e.target.error);
        });
    }

    async _delete(storeName, key) {
        const db = await this.init();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(storeName, 'readwrite');
            const store = tx.objectStore(storeName);
            store.delete(key);
            tx.oncomplete = () => resolve();
            tx.onerror = (e) => reject(e.target.error);
        });
    }

    async _clear(storeName) {
        const db = await this.init();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(storeName, 'readwrite');
            const store = tx.objectStore(storeName);
            store.clear();
            tx.oncomplete = () => resolve();
            tx.onerror = (e) => reject(e.target.error);
        });
    }

    async _count(storeName) {
        const db = await this.init();
        return new Promise((resolve, reject) => {
            const tx = db.transaction(storeName, 'readonly');
            const store = tx.objectStore(storeName);
            const request = store.count();
            request.onsuccess = () => resolve(request.result);
            request.onerror = (e) => reject(e.target.error);
        });
    }

    // ─────────────────────────────────────────────
    // Bootstrap sync — full data load
    // ─────────────────────────────────────────────

    async syncBootstrap(data) {
        if (data.produits) await this._putMany(STORES.PRODUITS, data.produits);
        if (data.stocks) {
            // Stocks have compound key [boutique_id, produit_id]
            await this._putMany(STORES.STOCKS, data.stocks.map(s => ({
                ...s,
                boutique_id: s.boutique_id || data.boutique?.id,
            })));
        }
        if (data.grossistes) await this._putMany(STORES.GROSSISTES, data.grossistes);
        if (data.prix_grossistes) await this._putMany(STORES.PRIX_GROSSISTES, data.prix_grossistes);
        if (data.boutique) await this._put(STORES.BOUTIQUE, data.boutique);
    }

    /**
     * Delta sync — apply incremental changes.
     */
    async syncDelta(data) {
        if (data.produits?.length) await this._putMany(STORES.PRODUITS, data.produits);
        if (data.stocks?.length) await this._putMany(STORES.STOCKS, data.stocks);
        if (data.grossistes?.length) await this._putMany(STORES.GROSSISTES, data.grossistes);
        if (data.prix_grossistes?.length) await this._putMany(STORES.PRIX_GROSSISTES, data.prix_grossistes);
        if (data.boutique) await this._put(STORES.BOUTIQUE, data.boutique);
    }

    // ─────────────────────────────────────────────
    // Reads
    // ─────────────────────────────────────────────

    async getProduits() {
        return this._getAll(STORES.PRODUITS);
    }

    async getStock(boutiqueId, produitId) {
        return this._get(STORES.STOCKS, [boutiqueId, produitId]);
    }

    async getAllStocks() {
        return this._getAll(STORES.STOCKS);
    }

    async getGrossistes() {
        return this._getAll(STORES.GROSSISTES);
    }

    async getPrixGrossistes() {
        return this._getAll(STORES.PRIX_GROSSISTES);
    }

    async getBoutique() {
        const all = await this._getAll(STORES.BOUTIQUE);
        return all.length > 0 ? all[0] : null;
    }

    // ─────────────────────────────────────────────
    // Queue operations for offline sync
    // ─────────────────────────────────────────────

    async queueVente(venteData) {
        venteData.timestamp = new Date().toISOString();
        await this._put(STORES.PENDING_VENTES, venteData);

        // Decrement local stock
        const boutiqueId = venteData.data.boutique_id;
        const produitId = venteData.data.produit_id;
        const quantite = parseInt(venteData.data.quantite, 10);

        const stock = await this.getStock(boutiqueId, produitId);
        if (stock) {
            stock.quantite = Math.max(0, stock.quantite - quantite);
            await this._put(STORES.STOCKS, stock);
        }
    }

    async queueDepense(depenseData) {
        depenseData.timestamp = new Date().toISOString();
        await this._put(STORES.PENDING_DEPENSES, depenseData);
    }

    async queueTransfert(transfertData) {
        transfertData.timestamp = new Date().toISOString();
        await this._put(STORES.PENDING_TRANSFERTS, transfertData);
    }

    // ─────────────────────────────────────────────
    // Pending operations management
    // ─────────────────────────────────────────────

    async getPendingVentes() {
        return this._getAll(STORES.PENDING_VENTES);
    }

    async getPendingDepenses() {
        return this._getAll(STORES.PENDING_DEPENSES);
    }

    async getPendingTransferts() {
        return this._getAll(STORES.PENDING_TRANSFERTS);
    }

    async getPendingCount() {
        const [v, d, t] = await Promise.all([
            this._count(STORES.PENDING_VENTES),
            this._count(STORES.PENDING_DEPENSES),
            this._count(STORES.PENDING_TRANSFERTS),
        ]);
        return v + d + t;
    }

    async getAllPendingOperations() {
        const [ventes, depenses, transferts] = await Promise.all([
            this.getPendingVentes(),
            this.getPendingDepenses(),
            this.getPendingTransferts(),
        ]);

        return [
            ...ventes.map((v) => ({ ...v, type: 'vente' })),
            ...depenses.map((d) => ({ ...d, type: d.data?.type === 'perte' ? 'perte' : 'depense' })),
            ...transferts.map((t) => ({ ...t, type: 'transfert' })),
        ].sort((a, b) => new Date(a.timestamp) - new Date(b.timestamp));
    }

    async removePending(type, clientUuid) {
        const storeMap = {
            vente: STORES.PENDING_VENTES,
            depense: STORES.PENDING_DEPENSES,
            perte: STORES.PENDING_DEPENSES,
            transfert: STORES.PENDING_TRANSFERTS,
        };
        const storeName = storeMap[type];
        if (storeName) {
            await this._delete(storeName, clientUuid);
        }
    }

    // ─────────────────────────────────────────────
    // Sync metadata
    // ─────────────────────────────────────────────

    async getLastSyncTimestamp() {
        const meta = await this._get(STORES.SYNC_META, 'last_sync');
        return meta?.value || null;
    }

    async setLastSyncTimestamp(timestamp) {
        await this._put(STORES.SYNC_META, { key: 'last_sync', value: timestamp });
    }

    async getSyncMeta(key) {
        const meta = await this._get(STORES.SYNC_META, key);
        return meta?.value || null;
    }

    async setSyncMeta(key, value) {
        await this._put(STORES.SYNC_META, { key, value });
    }
}

// Generate UUID v4
function generateUUID() {
    if (crypto.randomUUID) {
        return crypto.randomUUID();
    }
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        const v = c === 'x' ? r : (r & 0x3) | 0x8;
        return v.toString(16);
    });
}

export { OfflineDB, generateUUID, STORES };
