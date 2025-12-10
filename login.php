<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <title>Document</title>
    <style>
        .auth-container {
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .error-message {
            animation: fadeIn 0.3s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body dir="rtl">
    <?php
    session_start();
    $error = isset($_SESSION['login_error']) ? $_SESSION['login_error'] : '';
    unset($_SESSION['login_error']); // پاک کردن ارور بعد از خواندن
    ?>

    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-md auth-container">
            <div class="text-center mb-8">
                <h1 class="text-4xl font-bold mb-2">سپهری راد</h1>
                <p class="text-black/60 text-lg">حضور غیاب انلاین دانش آموزان</p>
            </div>
            <div class="bg-white/95 backdrop-blur-sm rounded-3xl p-8 shadow-2xl border border-white/20">

                <!-- نمایش خطا اگر وجود داشته باشد -->
                <?php if ($error): ?>
                    <div class="mb-6 error-message">
                        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- فرم ورود -->
                <form action="login_action.php" method="POST" class="auth-form space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2" id="login-username-label">نام کاربری</label>
                        <input name="username" id="login-username" type="text" placeholder="نام کاربری خود را وارد کنید"
                            class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-gray-700 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">رمز عبور</label>
                        <div class="relative">
                            <input name="password" id="login-password" type="password" placeholder="رمز عبور خود را وارد کنید"
                                class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-gray-700 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200">
                            <button type="button" class="password-toggle absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-blue-600 transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="bg-blue-600 hover:bg-primary w-full py-3 px-4 text-white font-medium rounded-xl transition-all duration-300">
                        ورود
                    </button>
                </form>
            </div>

        </div>
    </div>

    <script>
        const passwordInput = document.querySelector("#login-password")
        const toggle = document.querySelector(".password-toggle")

        toggle.addEventListener('click', function() {
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

        // تمرکز روی اولین فیلد اگر خطا وجود داشته باشد
        <?php if ($error): ?>
            document.addEventListener('DOMContentLoaded', function() {
                document.getElementById('login-username').focus();
            });
        <?php endif; ?>
    </script>
</body>

</html>