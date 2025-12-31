document.getElementById('loginForm').addEventListener('submit', function(e) {
    var email = document.getElementById('email').value.trim();
    var password = document.getElementById('password').value.trim();
    var errorDiv = document.getElementById('login-error');
    errorDiv.style.display = 'none';
    errorDiv.textContent = '';
    if (!email || !password) {
        errorDiv.textContent = 'Veuillez remplir tous les champs.';
        errorDiv.style.display = 'block';
        e.preventDefault();
        return;
    }
    if (!email.includes('@')) {
        errorDiv.textContent = 'Veuillez entrer une adresse e-mail valide (avec @).';
        errorDiv.style.display = 'block';
        e.preventDefault();
        return;
    }
    // Si tout est valide, redirige vers dashboard.html
    e.preventDefault();
    window.location.href = 'dashboard.html';
}); 