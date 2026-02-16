// public/js/teacherManager.js

class TeacherManager {
    constructor() {
        this.currentTeacherId = null;
        this.teacherToDelete = null;
        this.currentPage = 1;
        this.searchQuery = '';
        this.initializeEventListeners();
        this.initializeModals();
    }

    // مقداردهی اولیه event listeners
    initializeEventListeners() {
        // مدیریت جستجو
        const searchInput = document.getElementById('searchInput');
        let searchTimeout;

        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.searchQuery = e.target.value.trim();
                    this.searchTeachers(this.searchQuery);
                }, 500);
            });
        }

        // دکمه افزودن دبیر
        const addBtn = document.getElementById('addTeacherBtn');
        const addFirstBtn = document.getElementById('addFirstTeacherBtn');

        if (addBtn) addBtn.addEventListener('click', () => this.openAddModal());
        if (addFirstBtn) addFirstBtn.addEventListener('click', () => this.openAddModal());

        // دکمه‌های حذف و ویرایش در جدول (از طریق event delegation)
        document.addEventListener('click', (e) => {
            const editBtn = e.target.closest('[data-edit-teacher]');
            const deleteBtn = e.target.closest('[data-delete-teacher]');

            if (editBtn) {
                const teacherId = editBtn.dataset.editTeacher;
                this.editTeacher(parseInt(teacherId));
            }

            if (deleteBtn) {
                const teacherId = deleteBtn.dataset.deleteTeacher;
                const teacherName = deleteBtn.dataset.teacherName;
                this.showDeleteModal(parseInt(teacherId), teacherName);
            }
        });
    }

    // مقداردهی اولیه modalها
    initializeModals() {
        // Teacher Form Modal
        this.teacherModal = document.getElementById('teacherModal');
        this.modalOverlay = this.teacherModal?.querySelector('.modal-overlay');
        this.modalTitle = document.getElementById('modalTitle');
        this.teacherForm = document.getElementById('teacherForm');
        this.teacherIdInput = document.getElementById('teacherId');
        this.firstNameInput = document.getElementById('firstName');
        this.lastNameInput = document.getElementById('lastName');
        this.usernameInput = document.getElementById('username');
        this.passwordInput = document.getElementById('password');
        this.newPasswordInput = document.getElementById('newPassword');
        this.passwordField = document.getElementById('passwordField');
        this.newPasswordField = document.getElementById('newPasswordField');
        this.saveTeacherBtn = document.getElementById('saveTeacherBtn');
        this.saveLoading = document.getElementById('saveLoading');

        // Delete Confirmation Modal
        this.deleteModal = document.getElementById('deleteModal');
        this.deleteOverlay = this.deleteModal?.querySelector('.modal-overlay');
        this.deleteMessage = document.getElementById('deleteMessage');
        this.confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
        this.deleteLoading = document.getElementById('deleteLoading');

        // اضافه کردن event listeners برای modalها
        if (this.modalOverlay) {
            this.modalOverlay.addEventListener('click', () => this.closeModal());
        }

        if (this.deleteOverlay) {
            this.deleteOverlay.addEventListener('click', () => this.closeDeleteModal());
        }

        // دکمه ذخیره در فرم
        if (this.saveTeacherBtn) {
            this.saveTeacherBtn.addEventListener('click', () => this.saveTeacher());
        }

        // دکمه تأیید حذف
        if (this.confirmDeleteBtn) {
            this.confirmDeleteBtn.addEventListener('click', () => this.confirmDelete());
        }

        // دکمه‌های toggle password
        document.addEventListener('click', (e) => {
            if (e.target.closest('[data-toggle-password]')) {
                const inputId = e.target.closest('[data-toggle-password]').dataset.togglePassword;
                this.togglePassword(inputId);
            }
        });
    }

    // بارگذاری لیست دبیران
    async loadTeachers() {
        try {
            this.showLoading();

            const response = await apiService.request('/teachers');

            if (response.success) {
                this.displayTeachers(response.data);
                this.hideLoading();

                // مدیریت حالت خالی
                if (response.count === 0) {
                    this.showEmptyState();
                } else {
                    this.hideEmptyState();
                }
            } else {
                throw new Error('Failed to load teachers');
            }

        } catch (error) {
            console.error('Error loading teachers:', error);
            this.showError('خطا در بارگذاری لیست دبیران');
        }
    }

    // جستجوی دبیران
    async searchTeachers(query) {
        if (query.length === 0) {
            this.loadTeachers();
            return;
        }

        try {
            this.showLoading();

            const response = await apiService.request(`/teachers/search?q=${encodeURIComponent(query)}`);

            if (response.success) {
                this.displayTeachers(response.data);
                this.hideLoading();

                if (response.count === 0) {
                    this.showEmptyState(true);
                } else {
                    this.hideEmptyState();
                }
            }
        } catch (error) {
            console.error('Search error:', error);
            this.hideLoading();
            this.showFlashMessage('خطا در جستجو', 'error');
        }
    }

    // نمایش دبیران در جدول
    displayTeachers(teachers) {
        const teachersList = document.getElementById('teachersList');
        if (!teachersList) return;

        teachersList.innerHTML = '';

        if (teachers.length === 0) {
            this.showEmptyState(true);
            return;
        }

        teachers.forEach((teacher, index) => {
            const row = document.createElement('tr');
            row.className = 'hover:bg-gray-50 transition-colors';
            row.innerHTML = this.createTeacherRowHTML(teacher, index);
            teachersList.appendChild(row);
        });
    }

    // ایجاد HTML برای هر ردیف دبیر
    createTeacherRowHTML(teacher, index) {
        const fullName = `${teacher.first_name} ${teacher.last_name}`;
        const createdDate = new Date(teacher.created_at).toLocaleDateString('fa-IR');

        return `
            <td class="px-4 py-3 text-sm text-gray-900">${index + 1}</td>
            <td class="px-4 py-3 text-sm font-medium text-gray-900">
                ${teacher.first_name} ${teacher.last_name}
            </td>
            <td class="px-4 py-3 text-sm text-gray-600">${teacher.username}</td>
            <td class="px-4 py-3 text-sm text-gray-500 hidden md:table-cell">
                ${createdDate}
            </td>
            <td class="px-4 py-3 text-center">
                <div class="flex justify-center gap-2">
                    <button data-edit-teacher="${teacher.id}"
                        class="px-3 py-1.5 bg-yellow-500 text-white text-sm font-medium rounded-lg hover:bg-yellow-600 transition-colors">
                        <i class="fas fa-edit ml-1"></i>
                        ویرایش
                    </button>
                    <button data-delete-teacher="${teacher.id}"
                        data-teacher-name="${fullName}"
                        class="px-3 py-1.5 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition-colors">
                        <i class="fas fa-trash ml-1"></i>
                        حذف
                    </button>
                </div>
            </td>
        `;
    }

    // ویرایش دبیر
    async editTeacher(teacherId) {
        await this.openEditModal(teacherId);
    }

    // باز کردن modal برای افزودن دبیر
    openAddModal() {
        this.currentTeacherId = null;
        this.openModal();
    }

    // باز کردن modal برای ویرایش دبیر
    async openEditModal(teacherId) {
        try {
            this.currentTeacherId = teacherId;
            this.showLoadingInModal();

            const response = await apiService.request(`/teachers/get?id=${teacherId}`);

            if (response.success) {
                const teacher = response.data;
                this.openModal(teacher);
            } else {
                throw new Error('Teacher not found');
            }
        } catch (error) {
            console.error('Error loading teacher data:', error);
            this.showFlashMessage('خطا در بارگذاری اطلاعات دبیر', 'error');
        } finally {
            this.hideLoadingInModal();
        }
    }

    // باز کردن modal
    openModal(teacherData = null) {
        if (!this.teacherModal) return;

        // تنظیم عنوان modal
        this.modalTitle.textContent = teacherData ? 'ویرایش دبیر' : 'افزودن دبیر جدید';

        // نمایش/مخفی کردن فیلدهای رمز عبور
        if (teacherData) {
            this.passwordField.classList.add('hidden');
            this.newPasswordField.classList.remove('hidden');
            this.fillFormWithTeacherData(teacherData);
        } else {
            this.passwordField.classList.remove('hidden');
            this.newPasswordField.classList.add('hidden');
            this.resetForm();
        }

        // نمایش modal
        this.teacherModal.classList.remove('hidden');

        // فوکوس روی اولین فیلد
        setTimeout(() => {
            this.firstNameInput?.focus();
        }, 100);
    }

    // بستن modal
    closeModal() {
        if (!this.teacherModal) return;
        this.teacherModal.classList.add('hidden');
        this.resetForm();
        this.currentTeacherId = null;
    }

    // پر کردن فرم با اطلاعات دبیر
    fillFormWithTeacherData(teacher) {
        this.teacherIdInput.value = teacher.id;
        this.firstNameInput.value = teacher.first_name || '';
        this.lastNameInput.value = teacher.last_name || '';
        this.usernameInput.value = teacher.username || '';
        this.newPasswordInput.value = '';

        // پاک کردن پیام‌های خطا
        this.clearFormErrors();
    }

    // ریست فرم
    resetForm() {
        if (this.teacherForm) {
            this.teacherForm.reset();
            this.teacherIdInput.value = '';
            this.clearFormErrors();
        }
    }

    // پاک کردن پیام‌های خطای فرم
    clearFormErrors() {
        const errorElements = [
            'firstNameError', 'lastNameError', 'usernameError',
            'passwordError', 'newPasswordError', 'formErrors'
        ];

        errorElements.forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = '';
                element.classList.add('hidden');
            }
        });
    }

    // نمایش خطا در فرم
    showFormError(elementId, message) {
        const element = document.getElementById(elementId);
        if (element) {
            element.textContent = message;
            element.classList.remove('hidden');
        }
    }

    // نمایش/مخفی کردن رمز عبور
    togglePassword(inputId) {
        const input = document.getElementById(inputId);
        if (!input) return;

        const toggleBtn = input.nextElementSibling;
        const eyeIcon = toggleBtn?.querySelector('i');

        if (input.type === 'password') {
            input.type = 'text';
            if (eyeIcon) {
                eyeIcon.className = 'fas fa-eye-slash';
            }
        } else {
            input.type = 'password';
            if (eyeIcon) {
                eyeIcon.className = 'fas fa-eye';
            }
        }
    }

    // نمایش حالت لودینگ در modal
    showLoadingInModal() {
        if (this.saveLoading) {
            this.saveLoading.classList.remove('hidden');
        }
        if (this.saveTeacherBtn) {
            this.saveTeacherBtn.disabled = true;
        }
    }

    // مخفی کردن حالت لودینگ در modal
    hideLoadingInModal() {
        if (this.saveLoading) {
            this.saveLoading.classList.add('hidden');
        }
        if (this.saveTeacherBtn) {
            this.saveTeacherBtn.disabled = false;
        }
    }

    // ذخیره دبیر (افزودن یا ویرایش)
    async saveTeacher() {
        try {
            this.showLoadingInModal();
            this.clearFormErrors();

            // جمع‌آوری داده‌ها
            const teacherId = this.teacherIdInput.value;
            const formData = {
                first_name: this.firstNameInput.value.trim(),
                last_name: this.lastNameInput.value.trim(),
                username: this.usernameInput.value.trim()
            };

            // اعتبارسنجی اولیه
            if (!formData.first_name) {
                this.showFormError('firstNameError', 'نام الزامی است');
                throw new Error('نام الزامی است');
            }

            if (!formData.last_name) {
                this.showFormError('lastNameError', 'نام خانوادگی الزامی است');
                throw new Error('نام خانوادگی الزامی است');
            }

            if (!formData.username) {
                this.showFormError('usernameError', 'نام کاربری الزامی است');
                throw new Error('نام کاربری الزامی است');
            }

            // اضافه کردن رمز عبور بر اساس حالت
            if (teacherId) {
                formData.id = teacherId;
                const newPassword = this.newPasswordInput.value.trim();
                if (newPassword) {
                    if (newPassword.length < 6) {
                        this.showFormError('newPasswordError', 'رمز عبور باید حداقل ۶ کاراکتر باشد');
                        throw new Error('رمز عبور باید حداقل ۶ کاراکتر باشد');
                    }
                    formData.new_password = newPassword;
                }
            } else {
                const password = this.passwordInput.value;
                if (!password) {
                    this.showFormError('passwordError', 'رمز عبور الزامی است');
                    throw new Error('رمز عبور الزامی است');
                }
                if (password.length < 6) {
                    this.showFormError('passwordError', 'رمز عبور باید حداقل ۶ کاراکتر باشد');
                    throw new Error('رمز عبور باید حداقل ۶ کاراکتر باشد');
                }
                formData.password = password;
            }

            // ارسال به API
            const endpoint = teacherId ? '/teachers/update' : '/teachers';
            const response = await apiService.request(endpoint, {
                method: 'POST',
                body: JSON.stringify(formData)
            });

            if (response.success) {
                this.showFlashMessage(response.message, 'success');
                this.closeModal();
                await this.loadTeachers();
            } else {
                // نمایش خطاهای سرور
                if (response.error_code === 'USERNAME_EXISTS') {
                    this.showFormError('usernameError', 'این نام کاربری قبلا استفاده شده است');
                } else if (response.message) {
                    const formErrors = document.getElementById('formErrors');
                    if (formErrors) {
                        formErrors.textContent = response.message;
                        formErrors.classList.remove('hidden');
                    }
                }
                throw new Error(response.message || 'خطا در ذخیره اطلاعات');
            }

        } catch (error) {
            console.error('Error saving teacher:', error);
            if (error.message !== 'نام الزامی است' &&
                error.message !== 'نام خانوادگی الزامی است' &&
                error.message !== 'نام کاربری الزامی است' &&
                error.message !== 'رمز عبور الزامی است' &&
                error.message !== 'رمز عبور باید حداقل ۶ کاراکتر باشد') {
                this.showFlashMessage(error.message || 'خطا در ذخیره اطلاعات', 'error');
            }
        } finally {
            this.hideLoadingInModal();
        }
    }

    // نمایش modal تأیید حذف
    showDeleteModal(teacherId, teacherName) {
        this.teacherToDelete = teacherId;

        if (this.deleteMessage) {
            this.deleteMessage.textContent =
                `آیا از حذف دبیر "${teacherName}" اطمینان دارید؟ این عمل قابل بازگشت نیست.`;
        }

        if (this.deleteModal) {
            this.deleteModal.classList.remove('hidden');
        }
    }

    // بستن modal تأیید حذف
    closeDeleteModal() {
        if (this.deleteModal) {
            this.deleteModal.classList.add('hidden');
        }
        this.teacherToDelete = null;

        // ریست حالت دکمه حذف
        if (this.confirmDeleteBtn) {
            this.confirmDeleteBtn.disabled = false;
        }
        if (this.deleteLoading) {
            this.deleteLoading.classList.add('hidden');
        }
    }

    // تأیید و اجرای حذف
    async confirmDelete() {
        if (!this.teacherToDelete) return;

        try {
            // نمایش حالت لودینگ
            if (this.deleteLoading) this.deleteLoading.classList.remove('hidden');
            if (this.confirmDeleteBtn) this.confirmDeleteBtn.disabled = true;

            // ارسال درخواست حذف به API
            const response = await apiService.request('/teachers/delete', {
                method: 'POST',
                body: JSON.stringify({ id: this.teacherToDelete })
            });

            if (response.success) {
                this.showFlashMessage(response.message, 'success');
                this.closeDeleteModal();
                await this.loadTeachers();
            } else {
                this.showFlashMessage(response.message || 'خطا در حذف دبیر', 'error');
                this.closeDeleteModal();
            }

        } catch (error) {
            console.error('Error deleting teacher:', error);
            this.showFlashMessage('خطا در حذف دبیر', 'error');
            this.closeDeleteModal();
        }
    }

    // نمایش پیام (flash message)
    showFlashMessage(message, type = 'success') {
        const flashMessages = document.getElementById('flashMessages');
        if (!flashMessages) return;

        const messageDiv = document.createElement('div');
        messageDiv.className = `mb-4 p-4 rounded-lg fade-in ${type === 'success' ? 'bg-green-50 border border-green-200 text-green-800' :
            type === 'error' ? 'bg-red-50 border border-red-200 text-red-800' :
                'bg-blue-50 border border-blue-200 text-blue-800'
            }`;

        messageDiv.innerHTML = `
            <div class="flex justify-between items-center">
                <div class="flex items-center gap-2">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                    <span>${message}</span>
                </div>
                <button onclick="this.parentElement.parentElement.remove()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;

        flashMessages.appendChild(messageDiv);

        // حذف خودکار بعد از 5 ثانیه
        setTimeout(() => {
            if (messageDiv.parentElement) {
                messageDiv.remove();
            }
        }, 5000);
    }

    // نمایش حالت لودینگ صفحه
    showLoading() {
        const loadingState = document.getElementById('loadingState');
        const teachersTable = document.getElementById('teachersTable');
        const errorState = document.getElementById('errorState');

        if (loadingState) loadingState.classList.remove('hidden');
        if (teachersTable) teachersTable.classList.add('hidden');
        if (errorState) errorState.classList.add('hidden');
    }

    // مخفی کردن حالت لودینگ صفحه
    hideLoading() {
        const loadingState = document.getElementById('loadingState');
        if (loadingState) loadingState.classList.add('hidden');
    }

    // نمایش حالت خطا
    showError(message) {
        const errorState = document.getElementById('errorState');
        const errorMessage = document.getElementById('errorMessage');
        const teachersTable = document.getElementById('teachersTable');

        if (loadingState) loadingState.classList.add('hidden');
        if (teachersTable) teachersTable.classList.add('hidden');
        if (errorState) errorState.classList.remove('hidden');
        if (errorMessage) errorMessage.textContent = message;
    }

    // نمایش حالت خالی
    showEmptyState(isSearch = false) {
        const teachersList = document.getElementById('teachersList');
        const emptyState = document.getElementById('emptyState');

        if (teachersList) teachersList.classList.add('hidden');
        if (emptyState) {
            emptyState.classList.remove('hidden');

            if (isSearch) {
                emptyState.innerHTML = `
                    <i class="fas fa-search text-4xl text-gray-400 mb-3"></i>
                    <p class="text-gray-600 mb-4">هیچ دبیری با این مشخصات یافت نشد.</p>
                `;
            } else {
                emptyState.innerHTML = `
                    <i class="fas fa-user-graduate text-4xl text-gray-400 mb-3"></i>
                    <p class="text-gray-600 mb-4">هیچ دبیری ثبت نشده است.</p>
                    <button id="addFirstTeacherBtn"
                        class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                        افزودن اولین دبیر
                    </button>
                `;

                // اضافه کردن event listener به دکمه جدید
                const addFirstBtn = document.getElementById('addFirstTeacherBtn');
                if (addFirstBtn) {
                    addFirstBtn.addEventListener('click', () => this.openAddModal());
                }
            }
        }
    }

    // مخفی کردن حالت خالی
    hideEmptyState() {
        const teachersList = document.getElementById('teachersList');
        const emptyState = document.getElementById('emptyState');

        if (teachersList) teachersList.classList.remove('hidden');
        if (emptyState) emptyState.classList.add('hidden');
    }

    // شروع کار مدیر دبیران
    async start() {
        try {
            // بررسی احراز هویت
            const user = await apiService.checkAuth();
            if (!user || user.role !== 'admin') {
                window.location.href = '../index.html';
                return;
            }

            // بارگذاری اولیه دبیران
            await this.loadTeachers();

        } catch (error) {
            console.error('Error in teacher manager start:', error);
            window.location.href = '../index.html';
        }
    }
}

// ایجاد نمونه جهانی و شروع کار
document.addEventListener('DOMContentLoaded', () => {
    window.teacherManager = new TeacherManager();
    teacherManager.start();
});