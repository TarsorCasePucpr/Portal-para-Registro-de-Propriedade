// main.js — Interações de UI para login e cadastro

document.addEventListener('DOMContentLoaded', function () {

  // ─────────────────────────────────────────────
  // CSRF: buscar token do servidor antes de qualquer submit
  // ─────────────────────────────────────────────
  const csrfInput = document.getElementById('csrf-token');
  if (csrfInput) {
    fetch('../../backend/auth/get_csrf.php')
      .then(function (res) { return res.json(); })
      .then(function (data) { csrfInput.value = data.csrf || ''; })
      .catch(function () { console.error('Não foi possível obter o token CSRF.'); });
  }

  // ─────────────────────────────────────────────
  // MOSTRAR / OCULTAR SENHA
  // ─────────────────────────────────────────────
  configurarToggleSenha('toggle-senha',    'senha');
  configurarToggleSenha('toggle-confirmar', 'confirmar-senha');

  function configurarToggleSenha(idBotao, idInput) {
    const botao = document.getElementById(idBotao);
    const input = document.getElementById(idInput);
    if (!botao || !input) return;

    botao.addEventListener('click', function () {
      const visivel = input.type === 'text';
      input.type       = visivel ? 'password' : 'text';
      botao.textContent = visivel ? 'Mostrar' : 'Ocultar';
    });
  }

  // ─────────────────────────────────────────────
  // MÁSCARA E VALIDAÇÃO DE CPF (apenas na tela de cadastro)
  // ─────────────────────────────────────────────
  const campoCPF = document.getElementById('cpf');
  if (campoCPF) {
    campoCPF.addEventListener('input', function () {
      this.value = mascaraCPF(this.value);
    });

    campoCPF.addEventListener('blur', function () {
      if (!validarFormatoCPF(this.value)) {
        mostrarErro('erro-cpf', 'CPF inválido. Use o formato 000.000.000-00.');
      } else {
        limparErro('erro-cpf');
      }
    });
  }

  // ─────────────────────────────────────────────
  // INDICADOR DE FORÇA DE SENHA (apenas na tela de cadastro)
  // ─────────────────────────────────────────────
  const campoSenhaCadastro = document.getElementById('senha');
  const divForca           = document.getElementById('forca-senha');

  if (campoSenhaCadastro && divForca) {
    campoSenhaCadastro.addEventListener('input', function () {
      if (this.value === '') {
        divForca.textContent  = '';
        divForca.className    = '';
        return;
      }
      const resultado      = analisarForcaSenha(this.value);
      divForca.textContent = 'Senha: ' + resultado.texto;
      divForca.className   = 'forca-' + resultado.nivel;
    });
  }

  // ─────────────────────────────────────────────
  // LGPD: habilitar/desabilitar botão de cadastro
  // ─────────────────────────────────────────────
  const checkLGPD      = document.getElementById('aceite-lgpd');
  const btnCadastrar   = document.getElementById('btn-cadastrar');

  if (checkLGPD && btnCadastrar) {
    checkLGPD.addEventListener('change', function () {
      btnCadastrar.disabled = !this.checked;
    });
  }

  // ─────────────────────────────────────────────
  // VALIDAÇÃO DO FORMULÁRIO DE LOGIN
  // ─────────────────────────────────────────────
  const formLogin = document.getElementById('form-login');
  if (formLogin) {
    formLogin.addEventListener('submit', function (e) {
      let valido = true;

      const email = document.getElementById('email').value;
      const senha = document.getElementById('senha').value;

      if (!validarEmail(email)) {
        mostrarErro('erro-email', 'Digite um e-mail válido.');
        valido = false;
      } else {
        limparErro('erro-email');
      }

      if (senha.trim() === '') {
        mostrarErro('erro-senha', 'Digite sua senha.');
        valido = false;
      } else {
        limparErro('erro-senha');
      }

      if (!valido) {
        e.preventDefault();
        return;
      }

      // Estado de carregamento
      const btn = document.getElementById('btn-login');
      if (btn) {
        btn.textContent = 'Entrando...';
        btn.disabled    = true;
      }
    });
  }

  // ─────────────────────────────────────────────
  // VALIDAÇÃO DO FORMULÁRIO DE CADASTRO
  // ─────────────────────────────────────────────
  const formCadastro = document.getElementById('form-cadastro');
  if (formCadastro) {
    formCadastro.addEventListener('submit', function (e) {
      let valido = true;

      const nome     = document.getElementById('nome').value.trim();
      const email    = document.getElementById('email').value.trim();
      const cpf      = document.getElementById('cpf').value.trim();
      const senha    = document.getElementById('senha').value;
      const confirma = document.getElementById('confirmar-senha').value;
      const lgpd     = document.getElementById('aceite-lgpd').checked;

      if (nome === '' || nome.length > 100) {
        mostrarErro('erro-nome', 'Preencha seu nome completo.');
        valido = false;
      } else {
        limparErro('erro-nome');
      }

      if (!validarEmail(email)) {
        mostrarErro('erro-email', 'Digite um e-mail válido.');
        valido = false;
      } else {
        limparErro('erro-email');
      }

      if (!validarFormatoCPF(cpf)) {
        mostrarErro('erro-cpf', 'CPF inválido. Use o formato 000.000.000-00.');
        valido = false;
      } else {
        limparErro('erro-cpf');
      }

      if (!senhaValida(senha)) {
        mostrarErro('erro-senha', 'Senha fraca. Use mínimo 12 caracteres com maiúscula, minúscula, número e símbolo.');
        valido = false;
      } else {
        limparErro('erro-senha');
      }

      if (senha !== confirma) {
        mostrarErro('erro-confirmar', 'As senhas não coincidem.');
        valido = false;
      } else {
        limparErro('erro-confirmar');
      }

      if (!lgpd) {
        mostrarErro('erro-lgpd', 'Aceite os termos da LGPD para continuar.');
        valido = false;
      } else {
        limparErro('erro-lgpd');
      }

      if (!valido) {
        e.preventDefault();
        return;
      }

      // Estado de carregamento
      const btn = document.getElementById('btn-cadastrar');
      if (btn) {
        btn.textContent = 'Criando conta...';
        btn.disabled    = true;
      }
    });
  }

});
