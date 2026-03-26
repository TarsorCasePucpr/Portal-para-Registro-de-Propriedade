function validarEmail(email) {
  const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return regex.test(email.trim());
}

function validarFormatoCPF(cpf) {
  const regex = /^\d{3}\.\d{3}\.\d{3}-\d{2}$/;
  return regex.test(cpf.trim());
}

function mascaraCPF(valor) {
  return valor
    .replace(/\D/g, '')
    .slice(0, 11)
    .replace(/(\d{3})(\d)/, '$1.$2')
    .replace(/(\d{3})(\d)/, '$1.$2')
    .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
}

function analisarForcaSenha(senha) {
  let pontos = 0;

  if (senha.length >= 12)              pontos++;
  if (/[a-z]/.test(senha))            pontos++;
  if (/[A-Z]/.test(senha))            pontos++;
  if (/[0-9]/.test(senha))            pontos++;
  if (/[@$!%*?&]/.test(senha))        pontos++;

  if (pontos <= 2) return { nivel: 'fraca',  texto: 'Fraca' };
  if (pontos <= 3) return { nivel: 'média',  texto: 'Média' };
  return              { nivel: 'forte', texto: 'Forte' };
}

function senhaValida(senha) {
  return (
    senha.length >= 12 &&
    /[a-z]/.test(senha) &&
    /[A-Z]/.test(senha) &&
    /[0-9]/.test(senha) &&
    /[@$!%*?&]/.test(senha)
  );
}

function mostrarErro(idErro, mensagem) {
  const el = document.getElementById(idErro);
  if (el) el.textContent = mensagem;
}

function limparErro(idErro) {
  const el = document.getElementById(idErro);
  if (el) el.textContent = '';
}
document.addEventListener("DOMContentLoaded", () => {
  const senha = document.getElementById("nova-senha");
  const confirmar = document.getElementById("confirmar-nova-senha");
  const forca = document.getElementById("forca-senha");
  const form = document.getElementById("form-nova-senha");

  // só executa se estiver na página de redefinição
  if (!senha || !confirmar || !form) return;

  // =========================
  // 🔑 pegar token da URL
  // =========================
  const params = new URLSearchParams(window.location.search);
  const token = params.get("token");

  if (!token) {
    alert("Link inválido ou expirado");
    return;
  }

  const inputToken = document.getElementById("token");
  if (inputToken) inputToken.value = token;

  // =========================
  // 💪 força da senha
  // =========================
  senha.addEventListener("input", () => {
    const resultado = analisarForcaSenha(senha.value);
    if (forca) {
      forca.textContent = "Força: " + resultado.texto;
    }
  });

  // =========================
  // ✅ validação no submit
  // =========================
  form.addEventListener("submit", (e) => {

    limparErro("erro-senha");

    if (!senhaValida(senha.value)) {
      e.preventDefault();
      mostrarErro("erro-senha", "Senha não atende aos requisitos");
      return;
    }

    if (senha.value !== confirmar.value) {
      e.preventDefault();
      mostrarErro("erro-senha", "As senhas não coincidem");
      return;
    }
  });
});