(function () {
  if (localStorage.getItem('sng_cookie_consent')) return;

  const banner = document.createElement('div');
  banner.id = 'cookie-banner';
  banner.innerHTML =
    '<p>Este site usa cookies essenciais para funcionamento e segurança. ' +
    'Ao continuar, você concorda com nossa ' +
    '<a href="politica_privacidade.html">Política de Privacidade</a>.</p>' +
    '<div class="cookie-btns">' +
    '<button id="cookie-aceitar" class="btn btn-primary btn-sm">Aceitar</button>' +
    '<button id="cookie-recusar" class="btn btn-secondary btn-sm">Recusar</button>' +
    '</div>';
  document.body.appendChild(banner);

  document.getElementById('cookie-aceitar').addEventListener('click', function () {
    localStorage.setItem('sng_cookie_consent', 'accepted');
    banner.remove();
  });
  document.getElementById('cookie-recusar').addEventListener('click', function () {
    localStorage.setItem('sng_cookie_consent', 'declined');
    banner.remove();
  });
})();
