// public/js/app.js

// CSRF token for AJAX
const CSRF_TOKEN = document
    .querySelector('meta[name="csrf-token"]')
    .getAttribute("content");
const LOCALE = document.documentElement.lang || "en";

// Helper: Show toast notification
function showToast(message, type = "success") {
    const colorMap = {
        success: "success",
        error: "danger",
        warning: "warning text-dark",
    };
    const bgClass = colorMap[type] || "success";
    const toastEl = document.createElement("div");
    toastEl.className = `toast align-items-center text-white bg-${bgClass} border-0`;
    toastEl.setAttribute("role", "alert");
    toastEl.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>`;
    document.getElementById("toastContainer").appendChild(toastEl);
    const toast = new bootstrap.Toast(toastEl, { delay: 3000 });
    toast.show();
    toastEl.addEventListener("hidden.bs.toast", () => toastEl.remove());
}

// Helper: API call
async function apiCall(url, method = "GET", data = null) {
    const options = {
        method,
        credentials: "same-origin",
        headers: {
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": CSRF_TOKEN,
            Accept: "application/json",
        },
    };
    if (data) options.body = JSON.stringify(data);
    const res = await fetch(url, options);
    return res.json();
}

// Sidebar toggle for Safari
function toggleSidebar() {
    const sidebar = document.getElementById("sidebar");
    if (!sidebar) return;

    if (sidebar.classList.contains("show")) {
        sidebar.classList.remove("show");
        if (LOCALE === "ar") {
            sidebar.style.transform = "translateX(100%)";
        } else {
            sidebar.style.transform = "translateX(-100%)";
        }
    } else {
        sidebar.classList.add("show");
        sidebar.style.transform = "translateX(0)";
    }
}

// Format currency
function formatCurrency(amount) {
    return new Intl.NumberFormat(LOCALE === "ar" ? "ar-EG" : "en-US", {
        style: "currency",
        currency: "EGP",
        minimumFractionDigits: 2,
    }).format(amount || 0);
}

// Format date
function formatDate(date) {
    return new Date(date).toLocaleDateString(
        LOCALE === "ar" ? "ar-EG" : "en-US",
    );
}

// Stock Alert functions
async function fetchStockAlerts() {
    try {
        const res = await fetch("/dashboard/low-stock", {
            credentials: "same-origin",
            headers: { Accept: "application/json", "X-CSRF-TOKEN": CSRF_TOKEN },
        });
        return await res.json();
    } catch (e) {
        return null;
    }
}

function renderStockAlerts(data) {
    const isAr = LOCALE === "ar";
    const badge = document.getElementById("stockBadge");
    const list = document.getElementById("stockAlertsList");

    if (!data) {
        list.innerHTML = `<div class="text-center py-3 text-danger small">${isAr ? "فشل تحميل البيانات" : "Failed to load"}</div>`;
        return;
    }

    const total = data.total_alerts;
    if (total > 0) {
        badge.textContent = total > 99 ? "99+" : total;
        badge.classList.remove("d-none");
    } else {
        badge.classList.add("d-none");
    }

    if (total === 0) {
        list.innerHTML = `
            <div class="text-center py-4">
                <i class="fas fa-check-circle text-success fa-2x mb-2 d-block"></i>
                <span class="text-muted small">${isAr ? "المخزون بخير، لا توجد تنبيهات" : "All stock levels are fine"}</span>
            </div>`;
        return;
    }

    let html = "";

    if (data.out_of_stock && data.out_of_stock.length > 0) {
        html += `<div class="px-3 pt-2 pb-1">
            <span class="badge bg-danger mb-1">${isAr ? "نفد المخزون" : "Out of Stock"}</span>
        </div>`;
        data.out_of_stock.forEach((p) => {
            html += `<a href="/warehouse" class="dropdown-item d-flex align-items-center gap-2 py-2 border-bottom text-decoration-none">
                <span class="flex-shrink-0 text-danger"><i class="fas fa-times-circle"></i></span>
                <div class="flex-grow-1 min-width-0">
                    <div class="fw-semibold small text-truncate">${p.name}</div>
                    <div class="text-muted" style="font-size:0.72rem">
                        ${isAr ? "الكمية: " : "Qty: "}<strong class="text-danger">0</strong>
                        ${p.category ? " &bull; " + p.category : ""}
                    </div>
                </div>
                <span class="badge bg-danger bg-opacity-15 text-white border border-danger" style="font-size:0.65rem">${isAr ? "نفذ" : "Empty"}</span>
            </a>`;
        });
    }

    if (data.low_stock && data.low_stock.length > 0) {
        html += `<div class="px-3 pt-2 pb-1">
            <span class="badge bg-warning text-dark mb-1">${isAr ? "مخزون منخفض" : "Low Stock"}</span>
        </div>`;
        data.low_stock.forEach((p) => {
            html += `<a href="/warehouse" class="dropdown-item d-flex align-items-center gap-2 py-2 border-bottom text-decoration-none">
                <span class="flex-shrink-0 text-warning"><i class="fas fa-exclamation-triangle"></i></span>
                <div class="flex-grow-1 min-width-0">
                    <div class="fw-semibold small text-truncate">${p.name}</div>
                    <div class="text-muted" style="font-size:0.72rem">
                        ${isAr ? "الكمية: " : "Qty: "}<strong class="text-warning">${p.quantity}</strong>
                        ${isAr ? " / الحد الأدنى: " : " / Min: "}<strong>${p.min_stock}</strong>
                        ${p.category ? " &bull; " + p.category : ""}
                    </div>
                </div>
                <span class="badge bg-warning bg-opacity-15 text-white border border-warning" style="font-size:0.65rem">${isAr ? "منخفض" : "Low"}</span>
            </a>`;
        });
    }

    list.innerHTML = html;
}

window.loadStockAlerts = async function () {
    const isAr = LOCALE === "ar";
    const list = document.getElementById("stockAlertsList");
    if (!list) return;

    list.innerHTML = `<div class="text-center py-3 text-muted small"><i class="fas fa-spinner fa-spin me-1"></i>${isAr ? "جاري التحميل..." : "Loading..."}</div>`;
    const data = await fetchStockAlerts();
    renderStockAlerts(data);
};

// Initialize on page load
document.addEventListener("DOMContentLoaded", function () {
    // Add RTL/LTR class to body
    if (LOCALE === "ar") {
        document.body.classList.add("rtl");
    } else {
        document.body.classList.add("ltr");
    }

    // Initialize sidebar for mobile
    const sidebar = document.getElementById("sidebar");
    if (sidebar && window.innerWidth <= 768) {
        if (LOCALE === "ar") {
            sidebar.style.transform = "translateX(100%)";
        } else {
            sidebar.style.transform = "translateX(-100%)";
        }
    }

    // Load stock alerts badge count
    fetchStockAlerts().then((data) => {
        if (!data) return;
        const badge = document.getElementById("stockBadge");
        const total = data.total_alerts;
        if (total > 0 && badge) {
            badge.textContent = total > 99 ? "99+" : total;
            badge.classList.remove("d-none");
            if (total > 0) {
                const isAr = LOCALE === "ar";
                const msg = isAr
                    ? `⚠️ تنبيه: ${total} منتج ${total === 1 ? "قرب على النفاذ أو نفذ" : "منتجات قربت على النفاذ أو نفذت"} من المخزون`
                    : `⚠️ Alert: ${total} product${total > 1 ? "s" : ""} with low or no stock`;
                setTimeout(() => showToast(msg, "warning"), 1000);
            }
        }
    });
});

// Handle window resize
let resizeTimer;
window.addEventListener("resize", function () {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function () {
        const sidebar = document.getElementById("sidebar");
        if (!sidebar) return;

        if (window.innerWidth > 768) {
            sidebar.style.transform = "";
            sidebar.classList.remove("show");
        } else {
            if (!sidebar.classList.contains("show")) {
                if (LOCALE === "ar") {
                    sidebar.style.transform = "translateX(100%)";
                } else {
                    sidebar.style.transform = "translateX(-100%)";
                }
            }
        }
    }, 250);
});
