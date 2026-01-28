// frontend/js/app.js - مسیریابی اصلی

const routes = {
    '/': '/views/login.html',
    '/login': '/views/login.html',
    '/admin/dashboard': '/views/admin/dashboard.html',
    '/teacher/dashboard': '/views/teacher/dashboard.html'
};

// بارگذاری صفحه بر اساس مسیر
async function loadPage(path) {
    const route = routes[path] || '/views/404.html';

    try {
        const response = await fetch(route);

        if (!response.ok) {
            throw new Error('صفحه یافت نشد');
        }

        const html = await response.text();
        document.getElementById('app').innerHTML = html;

        // اجرای اسکریپت‌های مخصوص صفحه
        if (path === '/login') {
            // بارگذاری دینامیک auth.js
            await loadScript('/js/auth.js');
            if (typeof initLoginPage === 'function') {
                initLoginPage();
            }
        }

        // به روز رسانی URL در نوار آدرس (بدون رفرش)
        window.history.pushState({}, '', path);

    } catch (error) {
        console.error('خطا در بارگذاری صفحه:', error);
        document.getElementById('app').innerHTML = '<div class="p-8 text-center">خطا در بارگذاری صفحه</div>';
    }
}

// بارگذاری دینامیک اسکریپت
function loadScript(src) {
    return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = src;
        script.onload = resolve;
        script.onerror = reject;
        document.head.appendChild(script);
    });
}

// مدیریت کلیک روی لینک‌ها
function setupNavigation() {
    document.addEventListener('click', (e) => {
        const link = e.target.closest('a[data-nav]');
        if (link) {
            e.preventDefault();
            const path = link.getAttribute('href');
            loadPage(path);
        }
    });
}

// وقتی کاربر دکمه عقب/جلو مرورگر را می‌زند
window.addEventListener('popstate', () => {
    loadPage(window.location.pathname);
});

// شروع برنامه
document.addEventListener('DOMContentLoaded', () => {
    setupNavigation();

    // بارگذاری صفحه اول بر اساس مسیر فعلی
    const initialPath = window.location.pathname;
    loadPage(initialPath || '/');
});