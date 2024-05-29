jQuery(document).ready(function($) {
    $("#cadastrar-usuarios-form").submit(function(event) {
        var usuarios = $(this).find("textarea[name='usuarios']").val().split("\n").length;
        var progress = $("#progress-bar");
        
        progress.attr("max", usuarios);
        progress.val(0);
        
        // Simular o progresso (substitua isso pelo seu próprio código AJAX)
        var i = 0;
        var interval = setInterval(function() {
            if (i >= usuarios) {
                clearInterval(interval);
                return;
            }
            i++;
            progress.val(i);
        }, 200); // 200ms para simular o tempo de cada requisição
    });
});
