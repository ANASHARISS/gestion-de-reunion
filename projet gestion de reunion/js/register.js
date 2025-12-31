document.getElementById('registerForm').addEventListener('submit', function(e) {
    var fullname = document.getElementById('fullname').value.trim();
    var email = document.getElementById('reg-email').value.trim();
    var password = document.getElementById('reg-password').value.trim();
    var confirm = document.getElementById('reg-confirm').value.trim();
    var errorDiv = document.getElementById('register-error');
    errorDiv.style.display = 'none';
    errorDiv.textContent = '';
    if (!fullname || !email || !password || !confirm) {
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
    if (!/^(?=.*[a-z])(?=.*[A-Z]).{7,}$/.test(password)) {
        errorDiv.textContent = 'Le mot de passe doit contenir au moins 7 caractères, une majuscule et une minuscule.';
        errorDiv.style.display = 'block';
        e.preventDefault();
        return;
    }
    if (password !== confirm) {
        errorDiv.textContent = 'Les mots de passe ne correspondent pas.';
        errorDiv.style.display = 'block';
        e.preventDefault();
        return;
    }
});

// Gestion de l'affichage/masquage du mot de passe (inutile si plus d'icône, mais conservé si besoin)
document.querySelectorAll('.toggle-password').forEach(function(eye) {
    eye.addEventListener('click', function() {
        var target = document.getElementById(this.getAttribute('data-target'));
        if (target.type === 'password') {
            target.type = 'text';
            this.textContent = '🙈';
        } else {
            target.type = 'password';
            this.textContent = '👁️';
        }
    });
}); 