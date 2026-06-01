import "./bootstrap";

import Alpine from "alpinejs";

window.Alpine = Alpine;

Alpine.start();
// Toggle password visibility without jQuery (if used in the UI)
document.addEventListener("click", function (e) {
    const btn = e.target.closest(".password");
    if (!btn) return;
    const input = document.getElementById("password");
    if (!input) return;
    input.type = input.type === "password" ? "text" : "password";
});

// ─────────────────────────────────────────────
// PWA — Service Worker & Offline Infrastructure
// ─────────────────────────────────────────────

import { OfflineDB } from "./offline/offline-db.js";
import { SyncManager } from "./offline/sync-manager.js";
import { NetworkStatus } from "./offline/network-status.js";
import { OfflineForms } from "./offline/offline-forms.js";

// Register Service Worker
if ("serviceWorker" in navigator) {
    window.addEventListener("load", () => {
        navigator.serviceWorker
            .register("/sw.js")
            .then((reg) => {
                console.log("[PWA] Service Worker registered, scope:", reg.scope);

                // Check for updates every 5 minutes
                setInterval(() => reg.update(), 5 * 60 * 1000);
            })
            .catch((err) => {
                console.warn("[PWA] Service Worker registration failed:", err);
            });
    });
}

// Initialize offline infrastructure
async function initOffline() {
    try {
        const offlineDB = new OfflineDB();
        await offlineDB.init();

        const syncManager = new SyncManager(offlineDB);
        const networkStatus = new NetworkStatus(syncManager);
        const offlineForms = new OfflineForms(offlineDB, syncManager);

        // Expose globally for Alpine.js components
        window.ProsperousOffline = {
            offlineDB,
            syncManager,
            networkStatus,
            offlineForms,
        };

        // Bridge sync events to DOM for the offline-banner component
        syncManager.on("syncComplete", () => {
            document.dispatchEvent(new CustomEvent("sync-complete"));
        });

        syncManager.on("syncError", (data) => {
            // Only dispatch sync-error — the banner will check navigator.onLine
            // to decide if it's a real offline situation or just an API error
            document.dispatchEvent(new CustomEvent("sync-error"));
            console.warn("[PWA] Sync error:", data?.error);
        });

        syncManager.on("pendingCountChange", (data) => {
            document.dispatchEvent(
                new CustomEvent("pending-count-updated", {
                    detail: { count: data.count },
                })
            );
        });

        // Start monitoring network status
        networkStatus.startMonitoring();
        offlineForms.init();

        // Perform initial background sync if online (silently, no banner)
        if (navigator.onLine) {
            syncManager.fullSync().catch(() => {
                // Silently fail — user is online but API may not be ready
                console.warn("[PWA] Initial sync skipped or failed");
            });
        }

        console.log("[PWA] Offline infrastructure initialized");
    } catch (error) {
        console.error("[PWA] Failed to initialize offline:", error);
    }
}

// Initialize when DOM is ready
if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initOffline);
} else {
    initOffline();
}
