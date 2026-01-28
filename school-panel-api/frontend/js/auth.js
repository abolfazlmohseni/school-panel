// frontend/js/auth.js

// مدیریت نمایش/مخفی کردن رمز عبور
function initPasswordToggle() {
    const passwordInput = document.getElementById('login-password');
    const toggle = document.getElementById('password-toggle');

    if (!passwordInput || !toggle) return;

    toggle.addEventListener('click', function () {
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            this.innerHTML = `
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"></path>
                </svg>
            `;
        } else {
            passwordInput.type = 'password';
            this.innerHTML = `
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                </svg>
            `;
        }
    });
}

// نمایش خطا
function showError(message) {
    const errorDiv = document.getElementById('login-error');
    const errorText = document.getElementById('error-text');

    if (errorDiv && errorText) {
        errorText.textContent = message;
        errorDiv.style.display = 'block';

        // تمرکز روی فیلد نام کاربری
        document.getElementById('login-username').focus();
    }
}

// پنهان کردن خطا
function hideError() {
    const errorDiv = document.getElementById('login-error');
    if (errorDiv) {
        errorDiv.style.display = 'none';
    }
}

// ارسال فرم لاگین به API
async function handleLogin(event) {
    event.preventDefault();

    const username = document.getElementById('login-username').value.trim();
    const password = document.getElementById('login-password').value;

    if (!username || !password) {
        showError('لطفاً نام کاربری و رمز عبور را وارد کنید');
        return;
    }

    hideError();

    try {
        const response = await fetch('http://localhost/attendance_system/backend/api/auth/login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ username, password })
        });

        const data = await response.json();

        if (data.success) {
            // ذخیره اطلاعات کاربر در localStorage
            localStorage.setItem('user', JSON.stringify(data.user));
            localStorage.setItem('isLoggedIn', 'true');

            // هدایت به داشبورد بر اساس نقش کاربر
            if (data.user.role === 'admin') {
                window.location.href = '/admin/dashboard';
            } else {
                window.location.href = '/teacher/dashboard';
            }
        } else {
            showError(data.message);
        }
    } catch (error) {
        console.error('خطا در ارتباط با سرور:', error);
        showError('خطا در ارتباط با سرور');
    }
}

// مقداردهی اولیه صفحه لاگین
function initLoginPage() {
    initPasswordToggle();

    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', handleLogin);

        // اگر خطایی از URL بیاید (مثلاً بعد از logout)
        const urlParams = new URLSearchParams(window.location.search);
        const error = urlParams.get('error');
        if (error) {
            showError(decodeURIComponent(error));
        }
    }
}

// بررسی وضعیت ورود کاربر
function checkAuth() {
    const isLoggedIn = localStorage.getItem('isLoggedIn');
    const user = JSON.parse(localStorage.getItem('user') || 'null');

    return { isLoggedIn: isLoggedIn === 'true', user };
}

// خروج از سیستم
function logout() {
    localStorage.removeItem('user');
    localStorage.removeItem('isLoggedIn');
    window.location.href = '/login?error=' + encodeURIComponent('شما از سیستم خارج شدید');
}

// صادر کردن توابع برای استفاده در فایل‌های دیگر
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { initLoginPage, checkAuth, logout, handleLogin };
}