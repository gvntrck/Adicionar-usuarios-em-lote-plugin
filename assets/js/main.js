jQuery(document).ready(function($) {
    var emailTemplateEditor = null;
    var previewModal = $("#cul-template-preview-modal");
    var previewFrame = document.getElementById("cul-template-preview-frame");
    var previewSubject = $("#cul-template-preview-subject");

    function syncEmailTemplateEditor() {
        if (emailTemplateEditor && emailTemplateEditor.codemirror) {
            emailTemplateEditor.codemirror.save();
        }
    }

    function escapeHtml(value) {
        return String(value || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#39;");
    }

    function buildTemplatePreviewHtml(subject, bodyHtml) {
        var trimmedHtml = (bodyHtml || "").replace(/^\s+/, "");
        var safeSubject = escapeHtml(subject || "Previa da mensagem");

        if (/^(<!doctype\s+html\b|<html\b)/i.test(trimmedHtml)) {
            return bodyHtml;
        }

        return (
            "<!DOCTYPE html>" +
            '<html lang="pt-BR">' +
            "<head>" +
            '<meta charset="UTF-8">' +
            '<meta name="viewport" content="width=device-width, initial-scale=1.0">' +
            "<title>" + safeSubject + "</title>" +
            "<style>" +
            "body{margin:0;padding:24px;background:#f6f7f7;font-family:Arial,Helvetica,sans-serif;color:#1d2327;}" +
            ".cul-preview-shell{max-width:760px;margin:0 auto;padding:24px;background:#ffffff;border:1px solid #dcdcde;border-radius:12px;box-shadow:0 8px 28px rgba(0,0,0,.08);}" +
            "</style>" +
            "</head>" +
            "<body>" +
            '<div class="cul-preview-shell">' + (bodyHtml || "") + "</div>" +
            "</body>" +
            "</html>"
        );
    }

    function closeTemplatePreviewModal() {
        if (!previewModal.length) {
            return;
        }

        previewModal.attr("hidden", true);
        $("body").removeClass("cul-preview-modal-open");
    }

    if (
        typeof window.wp !== "undefined" &&
        wp.codeEditor &&
        typeof culAdminData !== "undefined" &&
        culAdminData.codeEditorSettings &&
        $("#cul-email-template-body").length
    ) {
        emailTemplateEditor = wp.codeEditor.initialize(
            document.getElementById("cul-email-template-body"),
            culAdminData.codeEditorSettings
        );
    }

    $(".cul-admin-card-form form").on("submit", function() {
        syncEmailTemplateEditor();
    });

    $("#cul-open-template-preview").on("click", function() {
        var subject;
        var bodyHtml;

        if (!previewModal.length || !previewFrame) {
            return;
        }

        syncEmailTemplateEditor();
        subject = $("#cul-template-subject").val().trim();
        bodyHtml = $("#cul-email-template-body").val();

        previewSubject.text(subject || "(Sem assunto)");
        previewFrame.srcdoc = buildTemplatePreviewHtml(subject, bodyHtml);
        previewModal.removeAttr("hidden");
        $("body").addClass("cul-preview-modal-open");
    });

    $(document).on("click", "[data-cul-modal-close]", function() {
        closeTemplatePreviewModal();
    });

    $(document).on("keydown", function(event) {
        if (event.key === "Escape") {
            closeTemplatePreviewModal();
        }
    });

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
