document.addEventListener("DOMContentLoaded", () => {
    // 1. Dynamic Framework Header Scroll Monitoring Matrix Logic
    const navbar = document.querySelector(".navbar");
    window.addEventListener("scroll", () => {
        if (window.scrollY > 50) {
            navbar.classList.add("scrolled");
        } else {
            navbar.classList.remove("scrolled");
        }
    });

    // 2. Global Presentation Design Theme Switching Controls Execution Hook
    const themeToggle = document.getElementById("themeToggle");
    if (themeToggle) {
        const storedTheme = localStorage.getItem("theme") || "light";
        document.documentElement.setAttribute("data-theme", storedTheme);
        themeToggle.textContent = storedTheme === "dark" ? "☀️ LIGHT" : "🌙 DARK";

        themeToggle.addEventListener("click", () => {
            const currentTheme = document.documentElement.getAttribute("data-theme");
            const targetTheme = currentTheme === "dark" ? "light" : "dark";
            
            document.documentElement.setAttribute("data-theme", targetTheme);
            localStorage.setItem("theme", targetTheme);
            themeToggle.textContent = targetTheme === "dark" ? "☀️ LIGHT" : "🌙 DARK";
        });
    }

    // 3. Password Input Cryptographic Structural Protection Visibility Unlocking Tool
    const togglePasswordElements = document.querySelectorAll(".password-toggle");
    togglePasswordElements.forEach(element => {
        element.addEventListener("click", function() {
            const inputField = document.getElementById(this.getAttribute("data-target"));
            if (inputField.type === "password") {
                inputField.type = "text";
                this.textContent = "👁️";
            } else {
                inputField.type = "password";
                this.textContent = "👁️‍🗨️";
            }
        });
    });

    // 4. Real-time Password Complexity Analysis & Evaluator Engine Block Hook
    const passwordInput = document.getElementById("registerPassword");
    const strengthBar = document.getElementById("strengthBar");
    if (passwordInput && strengthBar) {
        passwordInput.addEventListener("input", () => {
            const val = passwordInput.value;
            let score = 0;
            
            if (val.length >= 8) score++;
            if (/[A-Z]/.test(val)) score++;
            if (/[0-9]/.test(val)) score++;
            if (/[^\w]/.test(val)) score++;

            let color = "#EF4444";
            let width = "25%";
            if (score === 2) { color = "#F59E0B"; width = "50%"; }
            else if (score === 3) { color = "#3B82F6"; width = "75%"; }
            else if (score === 4) { color = "#22C55E"; width = "100%"; }
            
            if (val.length === 0) width = "0%";

            strengthBar.style.width = width;
            strengthBar.style.backgroundColor = color;
        });
    }
});