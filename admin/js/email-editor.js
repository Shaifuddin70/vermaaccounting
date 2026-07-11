(function () {
  'use strict';

  function readInitial(inputId) {
    var script = document.querySelector('.email-editor-initial[data-for="' + inputId + '"]');
    if (!script) {
      return '';
    }
    try {
      var data = JSON.parse(script.textContent || '{}');
      return typeof data.value === 'string' ? data.value : '';
    } catch (err) {
      return '';
    }
  }

  function isHtmlContent(value) {
    return /<[a-z][\s\S]*>/i.test(value);
  }

  function plainToHtml(value) {
    if (!value) {
      return '';
    }
    return value
      .split(/\r?\n\r?\n/)
      .map(function (block) {
        return '<p>' + block.replace(/\r?\n/g, '<br>') + '</p>';
      })
      .join('');
  }

  function normalizeInitial(value) {
    if (!value) {
      return '';
    }
    return isHtmlContent(value) ? value : plainToHtml(value);
  }

  function setUploading(button, uploading) {
    if (!button) {
      return;
    }
    button.disabled = uploading;
    button.classList.toggle('is-loading', uploading);
  }

  function initEditor(root) {
    if (!root || root.dataset.emailEditorReady === '1' || typeof Quill === 'undefined') {
      return;
    }

    var inputId = root.dataset.inputId || '';
    var output = document.getElementById(inputId);
    if (!output) {
      return;
    }

    var uploadUrl = root.dataset.uploadUrl || '/admin/email-editor-api';
    var csrf = root.dataset.csrf || '';
    var placeholder = root.dataset.placeholder || 'Write your message…';
    var initial = normalizeInitial(readInitial(inputId));

    var visualPanel = root.querySelector('[data-panel="visual"]');
    var htmlPanel = root.querySelector('[data-panel="html"]');
    var htmlTextarea = root.querySelector('.email-editor-html');
    var quillMount = root.querySelector('.email-editor-quill');
    var modeButtons = root.querySelectorAll('.email-editor-mode-btn');
    var imageButton = root.querySelector('[data-action="image"]');
    var linkButton = root.querySelector('[data-action="link"]');
    var imageInput = root.querySelector('.email-editor-image-input');
    var form = output.closest('form');

    var quill = new Quill(quillMount, {
      theme: 'snow',
      placeholder: placeholder,
      modules: {
        toolbar: [
          [{ header: [1, 2, 3, false] }],
          ['bold', 'italic', 'underline', 'strike'],
          [{ color: [] }, { background: [] }],
          [{ list: 'ordered' }, { list: 'bullet' }],
          [{ align: [] }],
          ['blockquote', 'code-block'],
          ['clean'],
        ],
      },
    });

    function setQuillHtml(html) {
      if (!html) {
        quill.setText('');
        return;
      }
      var delta = quill.clipboard.convert({ html: html });
      quill.setContents(delta, 'silent');
    }

    if (initial) {
      setQuillHtml(initial);
      htmlTextarea.value = quill.root.innerHTML;
    }
    output.value = htmlTextarea.value;

    var activeMode = 'visual';

    function syncOutput() {
      if (activeMode === 'html') {
        output.value = htmlTextarea.value.trim();
      } else {
        output.value = quill.root.innerHTML.trim();
      }
    }

    function setMode(mode) {
      if (mode === activeMode) {
        return;
      }

      if (mode === 'html') {
        htmlTextarea.value = quill.root.innerHTML;
      } else {
        setQuillHtml(htmlTextarea.value || '');
      }

      activeMode = mode;
      visualPanel.hidden = mode !== 'visual';
      htmlPanel.hidden = mode !== 'html';
      visualPanel.classList.toggle('is-active', mode === 'visual');
      htmlPanel.classList.toggle('is-active', mode === 'html');

      modeButtons.forEach(function (button) {
        var isActive = button.dataset.mode === mode;
        button.classList.toggle('is-active', isActive);
        button.setAttribute('aria-selected', isActive ? 'true' : 'false');
      });

      syncOutput();
    }

    modeButtons.forEach(function (button) {
      button.addEventListener('click', function () {
        setMode(button.dataset.mode || 'visual');
      });
    });

    quill.on('text-change', function () {
      if (activeMode === 'visual') {
        htmlTextarea.value = quill.root.innerHTML;
        syncOutput();
      }
    });

    htmlTextarea.addEventListener('input', function () {
      if (activeMode === 'html') {
        syncOutput();
      }
    });

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

    function insertImageUrl(url) {
      var range = quill.getSelection(true);
      quill.insertEmbed(range.index, 'image', url, 'user');
      quill.setSelection(range.index + 1);
      htmlTextarea.value = quill.root.innerHTML;
      syncOutput();
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
            if (activeMode === 'html') {
              var img = '<img src="' + url.replace(/"/g, '&quot;') + '" alt="" style="max-width:100%;height:auto;">';
              htmlTextarea.value += (htmlTextarea.value ? '\n' : '') + img;
              syncOutput();
              return;
            }
            insertImageUrl(url);
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

        if (activeMode === 'html') {
          var label = window.prompt('Link text', url) || url;
          var anchor = '<a href="' + url.replace(/"/g, '&quot;') + '">' + label + '</a>';
          htmlTextarea.value += (htmlTextarea.value ? ' ' : '') + anchor;
          syncOutput();
          return;
        }

        var range = quill.getSelection(true);
        if (range.length > 0) {
          quill.format('link', url);
        } else {
          quill.insertText(range.index, url, 'link', url);
        }
        htmlTextarea.value = quill.root.innerHTML;
        syncOutput();
      });
    }

    if (form) {
      form.addEventListener('submit', function () {
        syncOutput();
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
