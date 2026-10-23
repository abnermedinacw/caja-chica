const inputPassword = document.getElementById('password');
const botonOjo = document.getElementById('togglePassword');
botonOjo.addEventListener('click', () => {
    const mostrando = inputPassword.type === 'text';
    inputPassword.type = mostrando ? 'password' : 'text';
    botonOjo.innerHTML = mostrando ? botonOjo.dataset.iconoMostrar : botonOjo.dataset.iconoOcultar;
    botonOjo.setAttribute('aria-pressed', String(!mostrando));
    botonOjo.setAttribute('aria-label', mostrando ? 'Mostrar contraseña' : 'Ocultar contraseña');
});

const movimientoReducido = window.matchMedia('(prefers-reduced-motion: reduce)');
document.querySelectorAll('.moneda-juego').forEach(boton => {
    const moneda = boton.querySelector('svg');
    let animacion;
    let direccion = 1;
    boton.addEventListener('click', () => {
        if (!moneda.animate) return;
        animacion?.cancel();
        direccion *= -1;
        const salto = Math.min(60, moneda.clientHeight);
        const fotogramas = movimientoReducido.matches
            ? [{ opacity: 0.5 }, { opacity: 1 }]
            : [
                { transform: 'translateY(0) rotateY(0) rotate(0)' },
                { transform: `translateY(-${salto}px) rotateY(180deg) rotate(${direccion * 12}deg)`, offset: 0.45 },
                { transform: 'translateY(0) rotateY(360deg) rotate(0)' },
            ];
        animacion = moneda.animate(fotogramas, {
            duration: movimientoReducido.matches ? 150 : 650,
            easing: 'cubic-bezier(0.16, 1, 0.3, 1)',
        });
    });
});
