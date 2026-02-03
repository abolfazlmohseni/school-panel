// public/js/app.js

class ApiService {
    constructor(baseUrl = '/api') {
        this.baseUrl = baseUrl;
        this.user = this.getUserFromStorage();
    }

    // ذخیره اطلاعات کاربر
    setUser(user) {
        this.user = user;
        localStorage.setItem('user', JSON.stringify(user));
        localStorage.setItem('lastLogin', new Date().toISOString());
    }

    // دریافت اطلاعات کاربر از localStorage
    getUserFromStorage() {
        const userStr = localStorage.getItem('user');
        return userStr ? JSON.parse(userStr) : null;
    }

    // بررسی وضعیت لاگین
    async checkAuth() {
        try {
            const response = await fetch(`${this.baseUrl}/auth/check`, {
                credentials: 'include'
            });

            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            const result = await response.json();

            if (result.authenticated && result.user) {
                this.setUser(result.user);
                return result.user;
            } else {
                this.clearUser();
                return null;
            }

        } catch (error) {
            console.error('Auth check failed:', error);
            return null;
        }
    }

    // لاگین
    async login(username, password) {
        try {
            const response = await fetch(`${this.baseUrl}/auth/login`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify({ username, password })
            });

            const result = await response.json();

            if (result.success) {
                this.setUser(result.user);
                return { success: true, user: result.user };
            } else {
                return {
                    success: false,
                    error: result.message || 'Login failed'
                };
            }

        } catch (error) {
            console.error('Login error:', error);
            return {
                success: false,
                error: 'Network error. Please try again.'
            };
        }
    }

    // لاگاوت
    async logout() {
        try {
            const response = await fetch(`${this.baseUrl}/auth/logout`, {
                method: 'POST',
                credentials: 'include'
            });

            this.clearUser();
            return await response.json();

        } catch (error) {
            console.error('Logout error:', error);
            this.clearUser();
            return { success: false, error: 'Logout failed' };
        }
    }

    // دریافت پروفایل کاربر
    async getProfile() {
        try {
            const response = await fetch(`${this.baseUrl}/auth/profile`, {
                credentials: 'include'
            });

            const result = await response.json();

            if (result.success) {
                return { success: true, data: result.data };
            } else {
                return { success: false, error: result.message };
            }

        } catch (error) {
            console.error('Profile fetch error:', error);
            return { success: false, error: 'Failed to fetch profile' };
        }
    }

    // درخواست عمومی به API
    async request(endpoint, options = {}) {
        const defaultOptions = {
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json',
            }
        };

        const finalOptions = { ...defaultOptions, ...options };

        try {
            const response = await fetch(`${this.baseUrl}${endpoint}`, finalOptions);

            // بررسی وضعیت‌های خاص HTTP
            if (response.status === 401) {
                // Unauthorized - احتمالا session منقضی شده
                this.clearUser();
                window.location.href = '/public/index.html';
                return null;
            }

            if (response.status === 403) {
                // Forbidden - دسترسی ندارید
                throw new Error('دسترسی غیرمجاز');
            }

            if (response.status === 404) {
                // Not Found
                throw new Error('منبع مورد نظر یافت نشد');
            }

            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.message || 'Request failed');
            }

            return result;

        } catch (error) {
            console.error(`API Request failed (${endpoint}):`, error);
            throw error;
        }
    }

    // پاک کردن اطلاعات کاربر
    clearUser() {
        this.user = null;
        localStorage.removeItem('user');
        localStorage.removeItem('lastLogin');
    }

    // بررسی نقش کاربر
    hasRole(role) {
        return this.user && this.user.role === role;
    }

    isAdmin() {
        return this.hasRole('admin');
    }

    isTeacher() {
        return this.hasRole('teacher');
    }
}

// تابع‌های کمکی global
function showLoading(selector = 'body') {
    const element = document.querySelector(selector);
    if (element) {
        element.classList.add('loading');
    }
}

function hideLoading(selector = 'body') {
    const element = document.querySelector(selector);
    if (element) {
        element.classList.remove('loading');
    }
}

// ایجاد نمونه جهانی از ApiService
window.apiService = new ApiService();