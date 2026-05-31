// public/js/login.js

var _loginBusy = false;

function handleLogin(event) {
    if (event && event.preventDefault) {
        event.preventDefault();
    }

    if (_loginBusy) return;

    var tenantCode = (document.getElementById("tenant_code") || {}).value || "";
    tenantCode = tenantCode.trim();
    var username = document.getElementById("username").value.trim();
    var password = document.getElementById("password").value;
    var alertBox = document.getElementById("alertBox");
    var btn = document.getElementById("loginBtn");
    var loginText = document.getElementById("loginText");
    var spinner = document.getElementById("loginSpinner");

    // Hide any existing alerts
    alertBox.classList.add("d-none");

    // Validate inputs
    if (!tenantCode || !username || !password) {
        alertBox.textContent = "أدخل كود المتجر واسم المستخدم وكلمة المرور";
        alertBox.classList.remove("d-none");
        return;
    }

    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if (!csrfMeta) {
        alertBox.textContent = "حدث خطأ. أعد تحميل الصفحة.";
        alertBox.classList.remove("d-none");
        return;
    }

    // Disable button and show spinner
    _loginBusy = true;
    btn.disabled = true;
    loginText.classList.add("opacity-50");
    spinner.classList.remove("d-none");

    // Prepare login data
    var loginData = {
        tenant_code: tenantCode,
        username: username,
        password: password,
    };

    // Send login request
    fetch(window.LOGIN_URL || "/login", {
        method: "POST",
        credentials: "same-origin",
        headers: {
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": csrfMeta.content,
            Accept: "application/json",
            "X-Requested-With": "XMLHttpRequest",
        },
        body: JSON.stringify(loginData),
    })
        .then(function (res) {
            if (res.status === 419) {
                alertBox.textContent = "انتهت الجلسة. جاري تحديث الصفحة...";
                alertBox.classList.remove("d-none");
                setTimeout(function () {
                    window.location.reload();
                }, 1500);
                return null;
            }
            if (res.status === 429) {
                alertBox.textContent =
                    "محاولات كثيرة. انتظر دقيقة وحاول مجدداً.";
                alertBox.classList.remove("d-none");
                return null;
            }
            return res.json();
        })
        .then(function (data) {
            if (!data) return;
            if (data.success) {
                // Redirect on success
                window.location.href = data.redirect;
            } else {
                // Show error message
                alertBox.textContent = data.message || "بيانات خاطئة";
                alertBox.classList.remove("d-none");
            }
        })
        .catch(function (error) {
            console.error("Login error:", error);
            alertBox.textContent =
                "خطأ في الاتصال. تحقق من الإنترنت وحاول مجدداً.";
            alertBox.classList.remove("d-none");
        })
        .finally(function () {
            // Re-enable button and hide spinner
            _loginBusy = false;
            btn.disabled = false;
            loginText.classList.remove("opacity-50");
            spinner.classList.add("d-none");
        });
}

function togglePassword() {
    var p = document.getElementById("password");
    var i = document.getElementById("eyeIcon");
    if (!p || !i) return;

    if (p.type === "password") {
        p.type = "text";
        i.className = "fas fa-eye-slash";
    } else {
        p.type = "password";
        i.className = "fas fa-eye";
    }

    // Keep focus on the password field after toggling
    p.focus();
}

// DOM event binding - ensure it works after page load
document.addEventListener("DOMContentLoaded", function () {
    var form = document.getElementById("loginForm");
    var toggleBtn = document.getElementById("togglePasswordBtn");
    var loginBtn = document.getElementById("loginBtn");

    if (form) {
        // Remove any existing submit handlers
        form.removeEventListener("submit", handleLogin);
        // Add submit event listener
        form.addEventListener("submit", function (e) {
            e.preventDefault();
            handleLogin(e);
        });
    }

    if (loginBtn) {
        // Remove any existing click handlers
        loginBtn.removeEventListener("click", handleLogin);
        // Add click handler as backup
        loginBtn.addEventListener("click", function (e) {
            e.preventDefault();
            handleLogin(e);
        });
    }

    if (toggleBtn) {
        toggleBtn.removeEventListener("click", togglePassword);
        toggleBtn.addEventListener("click", togglePassword);

        // Improve touch response on mobile
        toggleBtn.addEventListener("touchstart", function (e) {
            e.preventDefault();
            togglePassword();
        });
    }

    // Enter-key navigation: tenant_code → username → password → submit
    var tenantInput  = document.getElementById("tenant_code");
    var usernameInput = document.getElementById("username");
    var passwordInput = document.getElementById("password");

    if (tenantInput) {
        tenantInput.addEventListener("keypress", function (e) {
            if (e.key === "Enter") { e.preventDefault(); if (usernameInput) usernameInput.focus(); }
        });
    }

    if (usernameInput) {
        usernameInput.addEventListener("keypress", function (e) {
            if (e.key === "Enter") {
                e.preventDefault();
                if (passwordInput) passwordInput.focus();
            }
        });
    }

    if (passwordInput) {
        passwordInput.addEventListener("keypress", function (e) {
            if (e.key === "Enter") {
                e.preventDefault();
                handleLogin(e);
            }
        });
    }
});

// Also run immediately in case DOMContentLoaded already fired
if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {});
} else {
    // DOM already loaded, bind events
    setTimeout(function () {
        var form = document.getElementById("loginForm");
        if (form && !form._eventsBound) {
            form._eventsBound = true;
            form.addEventListener("submit", function (e) {
                e.preventDefault();
                handleLogin(e);
            });

            var loginBtn = document.getElementById("loginBtn");
            if (loginBtn) {
                loginBtn.addEventListener("click", function (e) {
                    e.preventDefault();
                    handleLogin(e);
                });
            }

            var toggleBtn = document.getElementById("togglePasswordBtn");
            if (toggleBtn) {
                toggleBtn.addEventListener("click", togglePassword);
            }
        }
    }, 0);
}
