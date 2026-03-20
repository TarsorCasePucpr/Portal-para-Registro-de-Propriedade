// ===== CONTROLE DE ESTADO VIA URL =====
const params = new URLSearchParams(window.location.search);
const status = params.get("status");

const aguardando = document.getElementById("estado-aguardando");
const sucesso = document.getElementById("estado-sucesso");
const erro = document.getElementById("estado-erro");

if (status === "success") {
  if (aguardando) aguardando.style.display = "none";
  if (sucesso) sucesso.style.display = "block";
} else if (status === "error") {
  if (aguardando) aguardando.style.display = "none";
  if (erro) erro.style.display = "block";
}

// ===== REENVIAR E-MAIL =====
const btnReenviar = document.getElementById("btn-reenviar");
if (btnReenviar) {
  btnReenviar.addEventListener("click", () => {
    alert("Se o e-mail existir, um novo link será enviado.");
  });
}

// ===== FORMULÁRIO DE REENVIO =====
const formReenvio = document.getElementById("form-reenvio");
if (formReenvio) {
  formReenvio.addEventListener("submit", (e) => {
    e.preventDefault();
    alert("Se o e-mail existir, um novo link será enviado.");
  });
}

// ===== MASCARAR EMAIL =====
const email = localStorage.getItem("emailCadastro");

if (email) {
  const mascarado = email.replace(/(.{1}).+(@.+)/, "$1***$2");
  const emailInfo = document.getElementById("email-info");

  if (emailInfo) {
    emailInfo.innerHTML =
      `Enviamos um link de confirmação para <strong>${mascarado}</strong>`;
  }
}