/**
 * Prosperous Motos — Offline Forms Interceptor
 * Captures form submissions when offline and queues them in IndexedDB.
 */

import { generateUUID } from './offline-db.js';

class OfflineForms {
    constructor(offlineDB, syncManager) {
        this.offlineDB = offlineDB;
        this.syncManager = syncManager;
        this._initialized = false;
    }

    init() {
        if (this._initialized) return;
        this._initialized = true;

        // Intercept vente forms
        this._interceptVenteForm();

        // Intercept depense/perte forms
        this._interceptDepenseForm();

        // Intercept transfert forms
        this._interceptTransfertForm();
    }

    // ─────────────────────────────────────────────
    // Vente form interception
    // ─────────────────────────────────────────────

    _interceptVenteForm() {
        // Listen for the custom Alpine.js vente submission
        document.addEventListener('offline-vente', async (event) => {
            if (navigator.onLine) return; // Let normal flow handle it

            event.preventDefault();
            event.stopPropagation();

            const data = event.detail;
            const clientUuid = generateUUID();

            try {
                await this.offlineDB.queueVente({
                    client_uuid: clientUuid,
                    type: 'vente',
                    data: {
                        produit_id: data.produit_id,
                        quantite: data.quantite,
                        boutique_id: data.boutique_id,
                        is_grossiste: data.is_grossiste || false,
                        grossiste_id: data.grossiste_id || null,
                        prix_unitaire: data.prix_unitaire,
                        montant_total: data.montant_total,
                    },
                });

                this._showToast('Vente enregistrée localement ✓', 'success');
                this._updatePendingBadge();

                // Dispatch event for UI update
                document.dispatchEvent(new CustomEvent('offline-vente-saved', {
                    detail: { clientUuid, data },
                }));
            } catch (error) {
                console.error('Error queuing vente:', error);
                this._showToast('Erreur lors de la sauvegarde locale', 'error');
            }
        });
    }

    // ─────────────────────────────────────────────
    // Depense/Perte form interception
    // ─────────────────────────────────────────────

    _interceptDepenseForm() {
        document.addEventListener('offline-depense', async (event) => {
            if (navigator.onLine) return;

            event.preventDefault();
            event.stopPropagation();

            const data = event.detail;
            const clientUuid = generateUUID();

            try {
                await this.offlineDB.queueDepense({
                    client_uuid: clientUuid,
                    type: data.type || 'depense', // 'depense' or 'perte'
                    data: {
                        type: data.type || 'depense',
                        intitule: data.intitule,
                        description: data.description,
                        montant: data.montant,
                        produit_id: data.produit_id || null,
                        quantite: data.quantite || null,
                        raison: data.raison || null,
                        photo_base64: data.photo_base64 || null,
                    },
                });

                const label = data.type === 'perte' ? 'Perte' : 'Dépense';
                this._showToast(`${label} enregistrée localement ✓`, 'success');
                this._updatePendingBadge();
            } catch (error) {
                console.error('Error queuing depense:', error);
                this._showToast('Erreur lors de la sauvegarde locale', 'error');
            }
        });
    }

    // ─────────────────────────────────────────────
    // Transfert form interception
    // ─────────────────────────────────────────────

    _interceptTransfertForm() {
        document.addEventListener('offline-transfert', async (event) => {
            if (navigator.onLine) return;

            event.preventDefault();
            event.stopPropagation();

            const data = event.detail;
            const clientUuid = generateUUID();

            try {
                await this.offlineDB.queueTransfert({
                    client_uuid: clientUuid,
                    type: 'transfert',
                    data: {
                        produit_id: data.produit_id,
                        quantite_demandee: data.quantite_demandee,
                    },
                });

                this._showToast('Demande de transfert enregistrée localement ✓', 'success');
                this._updatePendingBadge();
            } catch (error) {
                console.error('Error queuing transfert:', error);
                this._showToast('Erreur lors de la sauvegarde locale', 'error');
            }
        });
    }

    // ─────────────────────────────────────────────
    // UI Helpers
    // ─────────────────────────────────────────────

    _showToast(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `offline-toast offline-toast--${type}`;
        toast.innerHTML = `
            <div class="offline-toast__icon">${type === 'success' ? '✓' : type === 'error' ? '✗' : 'ℹ'}</div>
            <div class="offline-toast__message">${message}</div>
        `;
        toast.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 12px;
            color: white;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 99999;
            animation: slideInUp 0.3s ease;
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
            background: ${type === 'success' ? 'linear-gradient(135deg, #059669, #10b981)' :
                          type === 'error' ? 'linear-gradient(135deg, #dc2626, #ef4444)' :
                          'linear-gradient(135deg, #2563eb, #3b82f6)'};
        `;

        document.body.appendChild(toast);
        setTimeout(() => {
            toast.style.animation = 'slideOutDown 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    async _updatePendingBadge() {
        const count = await this.offlineDB.getPendingCount();
        document.dispatchEvent(new CustomEvent('pending-count-updated', {
            detail: { count },
        }));
    }
}

export { OfflineForms };
