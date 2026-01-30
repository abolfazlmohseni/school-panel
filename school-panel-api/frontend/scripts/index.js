// تنظیمات API
const API_BASE_URL = 'http://localhost/backend/api';

// عناصر DOM
const loginForm = document.getElementById('loginForm');
const usernameInput = document.getElementById('username');
const passwordInput = document.getElementById('password');
const togglePasswordBtn = document.getElementById('togglePassword');
const submitBtn = document.getElementById('submitBtn');
const btnText = document.getElementById('btnText');
const loadingSpinner = document.getElementById('loadingSpinner');
const errorMessage = document.getElementById('errorMessage');
const successMessage = document.getElementById('successMessage');

// مدیریت نمایش/مخفی کردن رمز عبور
togglePasswordBtn.addEventListener('click', function () {
    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
    passwordInput.setAttribute('type', type);
    this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
});

// مدیریت ارسال فرم
loginForm.addEventListener('submit', async function (e) {
    e.preventDefault();

    // اعتبارسنجی اولیه
    if (!usernameInput.value.trim() || !passwordInput.value) {
        showError('لطفا نام کاربری و رمز عبور را وارد کنید');
        return;
    }

    // نمایش حالت لودینگ
    setLoadingState(true);
    clearMessages();

    try {
        const response = await fetch(`${API_BASE_URL}/auth/login`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'include', // مهم برای ارسال کوکی
            body: JSON.stringify({
                username: usernameInput.value.trim(),
                password: passwordInput.value
            })
        });

        const result = await response.json();

        if (result.success) {
            showSuccess('ورود موفقیت‌آمیز! در حال انتقال...');

            // ذخیره اطلاعات کاربر در localStorage برای دسترسی سریع
            localStorage.setItem('user', JSON.stringify(result.user));
            localStorage.setItem('lastLogin', new Date().toISOString());

            // انتقال به داشبورد بعد از 1.5 ثانیه
            setTimeout(() => {
                window.location.href = 'dashboard.html';
            }, 1500);

        } else {
            showError(result.message || 'خطا در ورود به سیستم');
            setLoadingState(false);
        }

    } catch (error) {
        console.error('Login error:', error);
        showError('خطا در ارتباط با سرور. لطفا دوباره تلاش کنید.');
        setLoadingState(false);
    }
});

// بررسی اگر کاربر از قبل لاگین کرده
async function checkAuthStatus() {
    try {
        const response = await fetch(`${API_BASE_URL}/auth/check`, {
            credentials: 'include'
        });

        const result = await response.json();

        if (result.authenticated) {
            // کاربر لاگین کرده، انتقال به داشبورد
            window.location.href = 'dashboard.html';
        }
    } catch (error) {
        console.log('User not authenticated');
    }
}

// توابع کمکی
function setLoadingState(isLoading) {
    if (isLoading) {
        submitBtn.disabled = true;
        btnText.textContent = 'در حال ورود...';
        loadingSpinner.classList.remove('hidden');
    } else {
        submitBtn.disabled = false;
        btnText.textContent = 'ورود به سیستم';
        loadingSpinner.classList.add('hidden');
    }
}

function showError(message) {
    errorMessage.textContent = message;
    errorMessage.classList.remove('hidden');
    errorMessage.classList.add('fade-in');
}

function showSuccess(message) {
    successMessage.textContent = message;
    successMessage.classList.remove('hidden');
    successMessage.classList.add('fade-in');
}

function clearMessages() {
    errorMessage.classList.add('hidden');
    successMessage.classList.add('hidden');
}

// بررسی وضعیت احراز هویت هنگام بارگذاری صفحه
document.addEventListener('DOMContentLoaded', checkAuthStatus);

// فوکوس روی فیلد نام کاربری
usernameInput.focus();