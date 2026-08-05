(function () {
  'use strict';

  function slugify(name) {
    return String(name || '')
      .toLowerCase()
      .trim()
      .replace(/[''`ʼ']/g, '')
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '') || 'holiday';
  }

  function cacheBust(url) {
    var base = String(url || '').split('?')[0];
    return base + '?v=' + String(Date.now());
  }

  function bannerImgTag(url) {
    return (
      '<img src="' +
      String(url || '').replace(/"/g, '&quot;') +
      '" alt="" data-email-template-image="1" style="width:100%; max-width:520px; border-radius:10px; display:block;">'
    );
  }

  function applyBannerUrl(html, url) {
    var img = bannerImgTag(url);
    var source = String(html || '');
    // Only replace the banner <img> itself — never match from the logo tag across the body.
    var marked = /<img\b(?=[^>]*\bdata-email-template-image\s*=\s*["']?1["']?)[^>]*>/i;
    if (marked.test(source)) {
      return source.replace(marked, img);
    }
    var holidayImg = /<img\b(?=[^>]*\/images\/email-holidays\/)[^>]*>/i;
    if (holidayImg.test(source)) {
      return source.replace(holidayImg, img);
    }
    var divider = /(<div[^>]*linear-gradient[^>]*>\s*<\/div>\s*<\/td>\s*<\/tr>)/i;
    if (divider.test(source)) {
      return source.replace(
        divider,
        '$1\n<tr>\n    <td align="center" style="padding:24px 48px;">\n       ' + img + '\n    </td>\n</tr>'
      );
    }
    return (
      source +
      '\n<tr>\n    <td align="center" style="padding:24px 48px;">\n       ' +
      img +
      '\n    </td>\n</tr>'
    );
  }

  function init(root) {
    if (!root || root.dataset.ready === '1') {
      return;
    }
    root.dataset.ready = '1';

    var fixedName = (root.dataset.fixedName || '').trim();
    var nameInput = fixedName ? null : document.getElementById(root.dataset.nameInputId || '');
    var body = document.getElementById(root.dataset.bodyId || '');
    var fileInput = root.querySelector('[data-file-input]');
    var preview = root.querySelector('[data-preview]');
    var expectedFile = root.querySelector('[data-expected-file]');
    var statusEl = root.querySelector('[data-status]');
    var uploadLabel = root.querySelector('[data-upload-label]');
    var uploadUrl = root.dataset.uploadUrl || '/admin/email-editor-api';
    var csrf = root.dataset.csrf || '';
    var lastSlug = slugify(currentNameInitial());

    function currentNameInitial() {
      if (fixedName) {
        return fixedName;
      }
      return nameInput ? String(nameInput.value || '').trim() : '';
    }

    function currentName() {
      return currentNameInitial();
    }

    function setStatus(message, isError) {
      if (!statusEl) {
        return;
      }
      statusEl.textContent = message || '';
      statusEl.classList.toggle('is-error', !!isError);
    }

    function refreshFilenameHint() {
      var name = currentName();
      var slug = slugify(name || 'holiday');
      var filename = slug + '.jpg';
      if (expectedFile) {
        var hasThumb = preview && preview.querySelector('[data-preview-img]');
        if (!hasThumb) {
          expectedFile.textContent = filename;
        }
      }
      var placeholderFile = root.querySelector('[data-filename]');
      if (placeholderFile) {
        placeholderFile.textContent = filename;
      }

      // Only rewrite the banner path when the holiday name/slug actually changes.
      if (body && name && slug !== lastSlug) {
        var previous = lastSlug;
        lastSlug = slug;
        var match = String(body.value || '').match(
          /\/images\/email-holidays\/([a-z0-9-]+)\.(jpe?g|png|gif|webp)/i
        );
        if (match && match[1] === previous) {
          body.value = applyBannerUrl(body.value, cacheBust('/images/email-holidays/' + filename));
          body.dispatchEvent(new Event('input', { bubbles: true }));
        } else if (String(body.value || '').indexOf('data-email-template-image') === -1) {
          body.value = applyBannerUrl(body.value, cacheBust('/images/email-holidays/' + filename));
          body.dispatchEvent(new Event('input', { bubbles: true }));
        }
      } else if (name) {
        lastSlug = slug;
      }
    }

    function showPreview(url) {
      if (!preview) {
        return;
      }
      preview.classList.add('has-image');
      var img = preview.querySelector('[data-preview-img]');
      if (!img) {
        preview.innerHTML = '';
        img = document.createElement('img');
        img.setAttribute('data-preview-img', '');
        img.alt = 'Template image preview';
        preview.appendChild(img);
      }
      img.src = cacheBust(url);
      if (uploadLabel) {
        uploadLabel.textContent = 'Replace image';
      }
      if (expectedFile && url) {
        var path = String(url).split('?')[0];
        expectedFile.textContent = path.slice(path.lastIndexOf('/') + 1);
      }
    }

    if (nameInput) {
      nameInput.addEventListener('input', refreshFilenameHint);
      nameInput.addEventListener('change', refreshFilenameHint);
    }

    if (!fileInput) {
      return;
    }

    fileInput.addEventListener('change', function () {
      var file = fileInput.files && fileInput.files[0];
      fileInput.value = '';
      if (!file) {
        return;
      }

      var name = currentName();
      if (!name) {
        setStatus('Enter the holiday name first.', true);
        return;
      }

      var formData = new FormData();
      formData.append('csrf_token', csrf);
      formData.append('action', 'upload_template_image');
      formData.append('name', name);
      formData.append('image', file);

      setStatus('Uploading…', false);
      fileInput.disabled = true;

      fetch(uploadUrl, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        cache: 'no-store',
      })
        .then(function (response) {
          return response.json();
        })
        .then(function (data) {
          if (!data.ok || !data.url) {
            throw new Error(data.error || 'Upload failed.');
          }
          var freshUrl = cacheBust(data.url);
          showPreview(freshUrl);
          if (body) {
            body.value = applyBannerUrl(String(body.value || ''), freshUrl);
            body.dispatchEvent(new Event('input', { bubbles: true }));
            if (typeof window.refreshEmailEditorPreviews === 'function') {
              window.refreshEmailEditorPreviews();
            }
          }
          lastSlug = slugify(name);
          setStatus('Image saved as ' + (data.filename || slugify(name) + '.jpg') + '. Save the email to keep it.', false);
        })
        .catch(function (err) {
          setStatus(err.message || 'Upload failed.', true);
        })
        .finally(function () {
          fileInput.disabled = false;
        });
    });
  }

  document.querySelectorAll('[data-email-template-image]').forEach(init);
})();
