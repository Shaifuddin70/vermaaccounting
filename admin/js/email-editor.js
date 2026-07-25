(function () {
  'use strict';

  function setUploading(button, uploading) {
    if (!button) {
      return;
    }
    button.disabled = uploading;
    button.classList.toggle('is-loading', uploading);
  }

  function insertAtCursor(textarea, text) {
    var start = textarea.selectionStart;
    var end = textarea.selectionEnd;
    var value = textarea.value;
    textarea.value = value.slice(0, start) + text + value.slice(end);
    var cursor = start + text.length;
    textarea.selectionStart = cursor;
    textarea.selectionEnd = cursor;
    textarea.focus();
  }

  function initEditor(root) {
    if (!root || root.dataset.emailEditorReady === '1') {
      return;
    }

    var inputId = root.dataset.inputId || '';
    var textarea = document.getElementById(inputId);
    if (!textarea) {
      return;
    }

    var uploadUrl = root.dataset.uploadUrl || '/admin/email-editor-api';
    var csrf = root.dataset.csrf || '';
    var imageButton = root.querySelector('[data-action="image"]');
    var linkButton = root.querySelector('[data-action="link"]');
    var imageInput = root.querySelector('.email-editor-image-input');
    var form = textarea.form;

    function currentYear() {
      return String(new Date().getFullYear());
    }

    // Show the live system year in the editor; keep {year} token when saving.
    textarea.value = String(textarea.value || '').replace(/\{year\}/g, currentYear());

    if (form) {
      form.addEventListener('submit', function () {
        textarea.value = String(textarea.value || '').replace(/©\s*20\d{2}/g, '© {year}');
      });
    }

    function uploadImage(file) {
      var formData = new FormData();
      formData.append('csrf_token', csrf);
      formData.append('action', 'upload_image');
      formData.append('image', file);

      setUploading(imageButton, true);

      return fetch(uploadUrl, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
      })
        .then(function (response) {
          return response.json();
        })
        .then(function (data) {
          if (!data.ok || !data.url) {
            throw new Error(data.error || 'Image upload failed.');
          }
          return data.url;
        })
        .finally(function () {
          setUploading(imageButton, false);
        });
    }

    if (imageButton && imageInput) {
      imageButton.addEventListener('click', function () {
        imageInput.click();
      });

      imageInput.addEventListener('change', function () {
        var file = imageInput.files && imageInput.files[0];
        imageInput.value = '';
        if (!file) {
          return;
        }

        uploadImage(file)
          .then(function (url) {
            var img = '<img src="' + url.replace(/"/g, '&quot;') + '" alt="" style="max-width:100%;height:auto;">';
            insertAtCursor(textarea, img);
          })
          .catch(function (error) {
            window.alert(error.message || 'Could not upload image.');
          });
      });
    }

    if (linkButton) {
      linkButton.addEventListener('click', function () {
        var url = window.prompt('Link URL');
        if (!url) {
          return;
        }
        var label = window.prompt('Link text', url) || url;
        var anchor = '<a href="' + url.replace(/"/g, '&quot;') + '">' + label + '</a>';
        insertAtCursor(textarea, anchor);
      });
    }

    root.dataset.emailEditorReady = '1';
  }

  function boot() {
    document.querySelectorAll('[data-email-editor]').forEach(initEditor);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
