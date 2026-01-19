(function () {
  function initAccordion() {
    const trees = document.querySelectorAll('ul.tree');
    if (!trees.length) return;

    trees.forEach(function (tree) {
      // Process direct li children
      const listItems = tree.querySelectorAll(':scope > li');
      listItems.forEach(function (li) {
        // Find direct child UL (submenu)
        const childUl = Array.from(li.children).find(function (c) {
          return c.tagName === 'UL';
        });

        if (childUl) {
          li.classList.add('has-children');

          // Find direct link if present
          const directLink = Array.from(li.children).find(function (c) {
            return c.tagName === 'A';
          });

          // Build header wrapper
          const header = document.createElement('div');
          header.className = 'sitemap-accordion-header';

          if (directLink) {
            // Move the link into a span so click area can toggle
            const wrapper = document.createElement('span');
            wrapper.className = 'sitemap-link-wrapper';
            wrapper.style.flex = '1';
            wrapper.style.cursor = 'pointer';
            wrapper.appendChild(directLink.cloneNode(true));

            // clicking label toggles
            wrapper.addEventListener('click', function () {
              toggleContent(button, content);
            });

            header.appendChild(wrapper);
            directLink.remove();
          }

          // Create toggle button
          const button = document.createElement('button');
          button.type = 'button';
          button.className = 'sitemap-toggle';
          button.setAttribute('aria-expanded', 'false');
          button.setAttribute('aria-label', 'Toggle submenu');
          button.innerHTML = '<i class="material-icons">expand_more</i>';

          header.appendChild(button);

          // Insert header before the submenu
          li.insertBefore(header, childUl);

          // Wrap childUl into content container
          const content = document.createElement('div');
          content.className = 'sitemap-accordion-content';
          li.insertBefore(content, header.nextElementSibling);
          content.appendChild(childUl);

          // Check if this is the only child in parent list - open by default
          const parentUl = li.parentElement;
          const siblings = Array.from(parentUl.children).filter(function(child) {
            return child.tagName === 'LI';
          });
          
          if (siblings.length === 1) {
            content.classList.add('is-open');
            button.classList.add('active');
            button.setAttribute('aria-expanded', 'true');
          }

          // Click handler
          button.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            toggleContent(button, content);
          });
        }
      });
    });

    function toggleContent(button, content) {
      if (!content) return;
      const isOpen = content.classList.contains('is-open');
      if (isOpen) {
        content.classList.remove('is-open');
        button.classList.remove('active');
        button.setAttribute('aria-expanded', 'false');
      } else {
        content.classList.add('is-open');
        button.classList.add('active');
        button.setAttribute('aria-expanded', 'true');
      }
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAccordion);
  } else {
    initAccordion();
  }
})();
