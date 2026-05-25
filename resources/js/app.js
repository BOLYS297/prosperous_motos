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
