// validação redefinição de senha:
document.addEventListener("DOMContentLoaded", () => {
  const senha = document.getElementById("nova-senha");
  const confirmar = document.getElementById("confirmar-nova-senha");
  const forca = document.getElementById("forca-senha");
  const form = document.getElementById("form-nova-senha");

  // pegar token da URL
  const params = new URLSearchParams(window.location.search);
  const token = params.get("token");

  if (!token) {
    document.getElementById("estado-formulario").style.display = "none";
    document.getElementById("estado-token-invalido").style.display = "block";
    return;
  }

  document.getElementById("token").value = token;

  // força da senha
  senha.addEventListener("input", () => {
    let valor = senha.value;
    let pontos = 0;

    if (valor.length >= 12) pontos++;
    if (/[A-Z]/.test(valor)) pontos++;
    if (/[a-z]/.test(valor)) pontos++;
    if (/\d/.test(valor)) pontos++;
    if (/[@$!%*?&]/.test(valor)) pontos++;

    if (pontos <= 2) forca.textContent = "Força: Fraca";
    else if (pontos <= 4) forca.textContent = "Força: Média";
    else forca.textContent = "Força: Forte";
  });

  // validação antes de enviar
  form.addEventListener("submit", (e) => {
    if (senha.value !== confirmar.value) {
      e.preventDefault();
      alert("As senhas não coincidem!");
    }
  });
});
///fim revalidação