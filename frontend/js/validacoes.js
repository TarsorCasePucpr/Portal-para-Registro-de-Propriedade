// validacoes.js — Funções de validação client-side
// Toda validação aqui é apenas UX — o backend sempre revalida

/**
 * Valida formato de e-mail básico.
 * @param {string} email
 * @returns {boolean}
 */
function validarEmail(email) {
  const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return regex.test(email.trim());
}

/**
 * Valida formato de CPF: 000.000.000-00
 * Só verifica o formato — a validação aritmética fica no backend.
 * @param {string} cpf
 * @returns {boolean}
 */
function validarFormatoCPF(cpf) {
  const regex = /^\d{3}\.\d{3}\.\d{3}-\d{2}$/;
  return regex.test(cpf.trim());
}

/**
 * Aplica máscara de CPF enquanto o usuário digita.
 * Exemplo: 12345678901 → 123.456.789-01
 * @param {string} valor
 * @returns {string}
 */
function mascaraCPF(valor) {
  return valor
    .replace(/\D/g, '')
    .slice(0, 11)
    .replace(/(\d{3})(\d)/, '$1.$2')
    .replace(/(\d{3})(\d)/, '$1.$2')
    .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
}

/**
 * Analisa a força de uma senha.
 * @param {string} senha
 * @returns {{ nivel: 'fraca'|'média'|'forte', texto: string }}
 */
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

/**
 * Valida se uma senha atende os requisitos mínimos do sistema.
 * @param {string} senha
 * @returns {boolean}
 */
function senhaValida(senha) {
  return (
    senha.length >= 12 &&
    /[a-z]/.test(senha) &&
    /[A-Z]/.test(senha) &&
    /[0-9]/.test(senha) &&
    /[@$!%*?&]/.test(senha)
  );
}

/**
 * Exibe uma mensagem de erro em um campo.
 * @param {string} idErro - ID do elemento span.erro
 * @param {string} mensagem
 */
function mostrarErro(idErro, mensagem) {
  const el = document.getElementById(idErro);
  if (el) el.textContent = mensagem;
}

/**
 * Limpa a mensagem de erro de um campo.
 * @param {string} idErro
 */
function limparErro(idErro) {
  const el = document.getElementById(idErro);
  if (el) el.textContent = '';
}
