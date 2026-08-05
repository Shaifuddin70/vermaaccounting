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

  function parseSample(raw) {
    try {
      return JSON.parse(raw || '{}') || {};
    } catch (e) {
      return {};
    }
  }

  function fillTokens(text, sample) {
    var year = String(sample.year || new Date().getFullYear());
    var out = String(text || '');
    var map = {
      '{client_name}': String(sample.client_name || 'Sample Client'),
      '{client_email}': String(sample.client_email || 'client@example.com'),
      '{sin}': String(sample.sin || 'SAMPLE-SIN'),
      '{cin}': String(sample.sin || 'SAMPLE-SIN'),
      '{company}': String(sample.company || 'Sample Company'),
      '{year}': year,
    };
    Object.keys(map).forEach(function (token) {
      out = out.split(token).join(map[token]);
    });
    out = out.replace(/©\s*(?:\{year\}|20\d{2})/g, '© ' + year);
    return out;
  }

  function preparePreviewHtml(html, sample) {
    var origin = window.location.origin || '';
    var out = fillTokens(html, sample);
    var bust = String(Date.now());

    // Make image paths load from this admin host (local preview of production URLs).
    if (origin) {
      out = out.replace(
        /(src\s*=\s*["'])https?:\/\/[^"'>\s]+(\/images\/[^"'?\s]+)(?:\?[^"']*)?(["'])/gi,
        '$1' + origin + '$2?v=' + bust + '$3'
      );
      out = out.replace(
        /(src\s*=\s*["'])(\/images\/[^"'?\s]+)(?:\?[^"']*)?(["'])/gi,
        '$1' + origin + '$2?v=' + bust + '$3'
      );
      out = out.replace(
        /(src\s*=\s*["'])https?:\/\/[^"'>\s]+(\/email-asset\/[^"'?\s]+)(?:\?[^"']*)?(["'])/gi,
        '$1' + origin + '$2?v=' + bust + '$3'
      );
    } else {
      out = out.replace(
        /(src\s*=\s*["'])([^"']*\/images\/email-holidays\/[^"'?\s]+)(?:\?[^"']*)?(["'])/gi,
        '$1$2?v=' + bust + '$3'
      );
    }

    // Ensure relative URLs inside srcdoc resolve against this site.
    if (origin && !/<base\b/i.test(out)) {
      var baseTag = '<base href="' + origin.replace(/"/g, '') + '/">';
      if (/<head[^>]*>/i.test(out)) {
        out = out.replace(/<head([^>]*)>/i, '<head$1>' + baseTag);
      } else {
        out = baseTag + out;
      }
    }

    return out;
  }

  function createPreviewFrame(existing) {
    var next = document.createElement('iframe');
    next.className = (existing && existing.className) || 'email-editor-preview-frame';
    next.title = (existing && existing.title) || 'Email preview';
    // allow-same-origin so logo/banner images from this site can load.
    // Scripts stay blocked (no allow-scripts).
    next.setAttribute('sandbox', 'allow-same-origin');
    next.setAttribute('data-preview-frame', '');
    next.hidden = false;
    return next;
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
    var sample = parseSample(root.dataset.sample);
    var subjectInput = document.getElementById(root.dataset.subjectId || '');
    var imageButton = root.querySelector('[data-action="image"]');
    var linkButton = root.querySelector('[data-action="link"]');
    var previewButton = root.querySelector('[data-action="preview"]');
    var previewLabel = previewButton ? previewButton.querySelector('[data-preview-label]') : null;
    var imageInput = root.querySelector('.email-editor-image-input');
    var previewPanel = root.querySelector('[data-preview-panel]');
    var previewFrame = root.querySelector('[data-preview-frame]');
    var previewSubject = root.querySelector('[data-preview-subject]');
    var previewEmpty = root.querySelector('[data-preview-empty]');
    var form = textarea.form;
    var previewMode = false;

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

    function renderPreview() {
      if (!previewPanel || !previewFrame) {
        return;
      }

      if (subjectInput && previewSubject) {
        var subject = fillTokens(subjectInput.value, sample).trim();
        if (subject !== '') {
          previewSubject.hidden = false;
          previewSubject.textContent = '';
          var label = document.createElement('strong');
          label.textContent = 'Subject:';
          previewSubject.appendChild(label);
          previewSubject.appendChild(document.createTextNode(' ' + subject));
        } else {
          previewSubject.hidden = true;
          previewSubject.textContent = '';
        }
      }

      var body = String(textarea.value || '');
      if (body.trim() === '') {
        if (previewEmpty) {
          previewEmpty.hidden = false;
        }
        previewFrame.hidden = true;
        try {
          previewFrame.removeAttribute('srcdoc');
          previewFrame.src = 'about:blank';
        } catch (e) {
          /* ignore */
        }
        return;
      }

      if (previewEmpty) {
        previewEmpty.hidden = true;
      }

      // Browsers often blank a reused iframe after it was display:none.
      // Rebuild a fresh frame each time preview is shown.
      var next = createPreviewFrame(previewFrame);
      previewFrame.replaceWith(next);
      previewFrame = next;

      // Assign after the frame is in a visible panel.
      window.requestAnimationFrame(function () {
        if (!previewMode || previewFrame !== next) {
          return;
        }
        next.srcdoc = preparePreviewHtml(body, sample);
      });
    }

    function clearPreviewFrame() {
      if (!previewFrame) {
        return;
      }
      try {
        previewFrame.removeAttribute('srcdoc');
        previewFrame.src = 'about:blank';
      } catch (e) {
        /* ignore */
      }
    }

    function setPreviewMode(on) {
      previewMode = !!on;
      root.classList.toggle('is-preview', previewMode);
      if (previewPanel) {
        previewPanel.hidden = !previewMode;
      }
      textarea.hidden = previewMode;
      if (previewButton) {
        previewButton.classList.toggle('is-active', previewMode);
        previewButton.setAttribute('aria-pressed', previewMode ? 'true' : 'false');
      }
      if (previewLabel) {
        previewLabel.textContent = previewMode ? 'Code' : 'Preview';
      }
      if (imageButton) {
        imageButton.disabled = previewMode;
      }
      if (linkButton) {
        linkButton.disabled = previewMode;
      }
      if (previewMode) {
        renderPreview();
      } else {
        clearPreviewFrame();
      }
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
        if (previewMode) {
          return;
        }
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
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
          })
          .catch(function (error) {
            window.alert(error.message || 'Could not upload image.');
          });
      });
    }

    if (linkButton) {
      linkButton.addEventListener('click', function () {
        if (previewMode) {
          return;
        }
        var url = window.prompt('Link URL');
        if (!url) {
          return;
        }
        var label = window.prompt('Link text', url) || url;
        var anchor = '<a href="' + url.replace(/"/g, '&quot;') + '">' + label + '</a>';
        insertAtCursor(textarea, anchor);
      });
    }

    if (previewButton) {
      previewButton.addEventListener('click', function () {
        setPreviewMode(!previewMode);
      });
    }

    textarea.addEventListener('input', function () {
      if (previewMode) {
        renderPreview();
      }
    });

    if (subjectInput) {
      subjectInput.addEventListener('input', function () {
        if (previewMode) {
          renderPreview();
        }
      });
    }

    root._emailEditorSetPreview = setPreviewMode;
    root._emailEditorRefreshPreview = renderPreview;
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

  window.refreshEmailEditorPreviews = function () {
    document.querySelectorAll('[data-email-editor].is-preview').forEach(function (root) {
      if (typeof root._emailEditorRefreshPreview === 'function') {
        root._emailEditorRefreshPreview();
      }
    });
  };
})();
