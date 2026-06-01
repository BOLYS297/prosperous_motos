{{-- Offline Status Banner --}}
<div id="offline-banner"
     x-data="offlineBanner()"
     x-show="visible"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0 -translate-y-2"
     x-transition:enter-end="opacity-100 translate-y-0"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100 translate-y-0"
     x-transition:leave-end="opacity-0 -translate-y-2"
     x-cloak
     :class="bannerClass"
     style="position:fixed;top:0;left:0;right:0;z-index:9999;padding:10px 20px;font-size:14px;font-weight:500;display:flex;align-items:center;justify-content:space-between;gap:12px;font-family:inherit;">

    <div style="display:flex;align-items:center;gap:10px;">
        {{-- Status icon --}}
        <template x-if="status === 'offline'">
            <span style="display:inline-flex;align-items:center;gap:6px;">
                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 5.636a9 9 0 0 1 0 12.728M5.636 18.364a9 9 0 0 1 0-12.728m2.828 9.9a5 5 0 0 1 0-7.072m7.072 0a5 5 0 0 1 0 7.072M13 12a1 1 0 1 1-2 0 1 1 0 0 1 2 0Z" />
                </svg>
                <span>Mode hors ligne — Les données seront synchronisées à la reconnexion</span>
            </span>
        </template>

        <template x-if="status === 'syncing'">
            <span style="display:inline-flex;align-items:center;gap:6px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation:spin 1s linear infinite;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                </svg>
                <span>Synchronisation en cours...</span>
            </span>
        </template>

        <template x-if="status === 'synced'">
            <span style="display:inline-flex;align-items:center;gap:6px;">
                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                <span>Synchronisation terminée ✓</span>
            </span>
        </template>
    </div>

    <div style="display:flex;align-items:center;gap:8px;">
        <template x-if="pendingCount > 0">
            <span style="background:rgba(255,255,255,0.2);padding:3px 10px;border-radius:20px;font-size:12px;"
                  x-text="pendingCount + ' opération' + (pendingCount > 1 ? 's' : '') + ' en attente'">
            </span>
        </template>
    </div>
</div>

<style>
    @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

    .offline-banner--offline {
        background: linear-gradient(135deg, #dc2626, #b91c1c);
        color: white;
    }
    .offline-banner--syncing {
        background: linear-gradient(135deg, #d97706, #b45309);
        color: white;
    }
    .offline-banner--synced {
        background: linear-gradient(135deg, #059669, #047857);
        color: white;
    }
    .offline-banner--online {
        display: none !important;
    }

    /* Push body content down when banner is visible */
    body.has-offline-banner {
        padding-top: 48px;
    }

    [x-cloak] { display: none !important; }

    @keyframes slideInUp {
        from { transform: translateY(20px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
    @keyframes slideOutDown {
        from { transform: translateY(0); opacity: 1; }
        to { transform: translateY(20px); opacity: 0; }
    }
</style>

<script>
    function offlineBanner() {
        return {
            status: navigator.onLine ? 'online' : 'offline',
            pendingCount: 0,
            visible: false,

            get bannerClass() {
                return `offline-banner--${this.status}`;
            },

            init() {
                // Listen ONLY for real network status changes (browser events)
                window.addEventListener('online', () => {
                    // Connection restored — show syncing state only if we were offline
                    if (this.status === 'offline') {
                        this.status = 'syncing';
                        this.visible = true;
                        document.body.classList.add('has-offline-banner');
                    }
                });

                window.addEventListener('offline', () => {
                    this.status = 'offline';
                    this.visible = true;
                    document.body.classList.add('has-offline-banner');
                });

                // Listen for sync completion (hide banner after success)
                document.addEventListener('sync-complete', () => {
                    if (this.status === 'syncing') {
                        this.status = 'synced';
                        this.visible = true;
                        setTimeout(() => {
                            this.visible = false;
                            this.status = 'online';
                            document.body.classList.remove('has-offline-banner');
                        }, 3000);
                    }
                });

                // Sync errors do NOT mean offline — only hide the syncing banner
                document.addEventListener('sync-error', () => {
                    if (navigator.onLine) {
                        // We're online but sync failed (API error) — hide banner, not offline
                        this.visible = false;
                        this.status = 'online';
                        document.body.classList.remove('has-offline-banner');
                    }
                    // If truly offline, keep the banner as-is
                });

                // Listen for pending count changes
                document.addEventListener('pending-count-updated', (e) => {
                    this.pendingCount = e.detail.count;
                    // Only show pending badge if offline
                    if (this.pendingCount > 0 && !navigator.onLine) {
                        this.visible = true;
                    }
                });

                // Initial state — ONLY show if truly offline
                if (!navigator.onLine) {
                    this.status = 'offline';
                    this.visible = true;
                    document.body.classList.add('has-offline-banner');
                }
            },
        };
    }
</script>
