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
