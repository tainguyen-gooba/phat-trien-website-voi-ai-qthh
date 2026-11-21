/**
 * dom-parser.js - Robust zero-dependency HTML/DOM inspector for FastFood E2E Tests.
 */

const VOID_TAGS = new Set([
  'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr'
]);

/**
 * Parses attributes from an HTML open tag string.
 *
 * @param {string} tagString - E.g. '<button class="btn" id="foo" hidden>'
 * @returns {object} Map of attribute key-value pairs
 */
function parseAttributes(tagString) {
  const match = tagString.match(/^<[a-zA-Z0-9_-]+([\s\S]*?)>$/);
  if (!match) return {};
  const rawAttrs = match[1];
  const attrs = {};
  const attrRegex = /([a-zA-Z0-9_:-]+)(?:\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s>]+)))?/g;
  let m;
  while ((m = attrRegex.exec(rawAttrs)) !== null) {
    const key = m[1].toLowerCase();
    const val = m[2] !== undefined ? m[2] : (m[3] !== undefined ? m[3] : (m[4] !== undefined ? m[4] : true));
    attrs[key] = val;
  }
  return attrs;
}

/**
 * Extracts element info (outerHTML, innerHTML, textContent, attributes) starting at startIndex.
 */
function extractElementAt(html, startIndex) {
  const openTagMatch = html.slice(startIndex).match(/^<([a-zA-Z0-9_-]+)([\s\S]*?)>/);
  if (!openTagMatch) return null;

  const tag = openTagMatch[1].toLowerCase();
  const openTagLength = openTagMatch[0].length;
  const isSelfClosing = openTagMatch[0].endsWith('/>') || VOID_TAGS.has(tag);

  const attributes = parseAttributes(openTagMatch[0]);
  const classList = typeof attributes.class === 'string' ? attributes.class.split(/\s+/).filter(Boolean) : [];

  if (isSelfClosing) {
    return {
      tag,
      id: attributes.id || null,
      attributes,
      classList,
      outerHTML: openTagMatch[0],
      innerHTML: '',
      textContent: '',
      hasAttribute: (name) => Object.prototype.hasOwnProperty.call(attributes, name.toLowerCase()),
      getAttribute: (name) => attributes[name.toLowerCase()] ?? null,
      hasClass: (cls) => classList.includes(cls)
    };
  }

  // Find matching closing tag
  let depth = 1;
  const rest = html.slice(startIndex + openTagLength);
  const tagRegex = new RegExp('<(/?)(' + tag + ')(?:\\s[^>]*)?>', 'gi');
  let m;
  while ((m = tagRegex.exec(rest)) !== null) {
    if (m[1] === '/') {
      depth--;
    } else if (!m[0].endsWith('/>')) {
      depth++;
    }

    if (depth === 0) {
      const innerHTML = rest.slice(0, m.index);
      const outerHTML = openTagMatch[0] + innerHTML + m[0];
      const textContent = innerHTML.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();

      return {
        tag,
        id: attributes.id || null,
        attributes,
        classList,
        outerHTML,
        innerHTML,
        textContent,
        hasAttribute: (name) => Object.prototype.hasOwnProperty.call(attributes, name.toLowerCase()),
        getAttribute: (name) => attributes[name.toLowerCase()] ?? null,
        hasClass: (cls) => classList.includes(cls)
      };
    }
  }

  // Fallback if unclosed
  const fallbackOuter = openTagMatch[0];
  return {
    tag,
    id: attributes.id || null,
    attributes,
    classList,
    outerHTML: fallbackOuter,
    innerHTML: '',
    textContent: '',
    hasAttribute: (name) => Object.prototype.hasOwnProperty.call(attributes, name.toLowerCase()),
    getAttribute: (name) => attributes[name.toLowerCase()] ?? null,
    hasClass: (cls) => classList.includes(cls)
  };
}

/**
 * Parses a simple selector token into matching criteria.
 */
function parseToken(token) {
  let tag = null;
  let id = null;
  const classes = [];
  const attrs = [];

  // Attribute selector [attr=val]
  const attrRegex = /\[([a-zA-Z0-9_-]+)(?:([~|^$*]?=)(?:"([^"]*)"|'([^']*)'|([^\]]+)))?\]/g;
  let cleanToken = token.replace(attrRegex, (_, name, op, v1, v2, v3) => {
    const val = v1 !== undefined ? v1 : (v2 !== undefined ? v2 : v3);
    attrs.push({ name: name.toLowerCase(), op: op || '=', val: val || '' });
    return '';
  });

  // ID selector #foo
  const idMatch = cleanToken.match(/#([a-zA-Z0-9_-]+)/);
  if (idMatch) {
    id = idMatch[1];
    cleanToken = cleanToken.replace(idMatch[0], '');
  }

  // Class selectors .bar
  const classMatches = cleanToken.matchAll(/\.([a-zA-Z0-9_-]+)/g);
  for (const cm of classMatches) {
    classes.push(cm[1]);
  }
  cleanToken = cleanToken.replace(/\.[a-zA-Z0-9_-]+/g, '');

  if (cleanToken.trim()) {
    tag = cleanToken.trim().toLowerCase();
  }

  return { tag, id, classes, attrs };
}

/**
 * Checks if a parsed element matches criteria.
 */
function elementMatches(element, criteria) {
  if (criteria.tag && element.tag !== criteria.tag) return false;
  if (criteria.id && element.id !== criteria.id) return false;
  for (const cls of criteria.classes) {
    if (!element.classList.includes(cls)) return false;
  }
  for (const attr of criteria.attrs) {
    if (!element.hasAttribute(attr.name)) return false;
    if (attr.op && attr.val) {
      const actual = String(element.getAttribute(attr.name));
      if (attr.op === '=' && actual !== attr.val) return false;
      if (attr.op === '^=' && !actual.startsWith(attr.val)) return false;
      if (attr.op === '$=' && !actual.endsWith(attr.val)) return false;
      if (attr.op === '*=' && !actual.includes(attr.val)) return false;
      if (attr.op === '~=' && !actual.split(/\s+/).includes(attr.val)) return false;
    }
  }
  return true;
}

/**
 * Queries the first element matching selector in the given HTML string.
 *
 * @param {string} html
 * @param {string} selector - E.g. '#messages-nav-badge', '[data-section="messages"]', '.ff-msg-image'
 * @returns {object|null}
 */
function querySelector(html, selector) {
  if (!html || !selector) return null;

  // Handle descendant selectors: e.g. '[data-section="messages"] #messages-nav-badge'
  const parts = selector.trim().split(/\s+/);
  if (parts.length > 1) {
    let currentHtml = html;
    let currentElem = null;
    for (const part of parts) {
      currentElem = querySelector(currentHtml, part);
      if (!currentElem) return null;
      currentHtml = currentElem.innerHTML;
    }
    return currentElem;
  }

  const criteria = parseToken(parts[0]);

  // Fast path for ID
  if (criteria.id && !criteria.tag && criteria.classes.length === 0 && criteria.attrs.length === 0) {
    const idRegex = new RegExp('<([a-zA-Z0-9_-]+)[^>]*?\\bid=[\'"]' + criteria.id + '[\'"][^>]*>', 'i');
    const m = idRegex.exec(html);
    if (!m) return null;
    return extractElementAt(html, m.index);
  }

  // General search
  const openTagRegex = /<([a-zA-Z0-9_-]+)(?:[\s\S]*?)>/g;
  let m;
  while ((m = openTagRegex.exec(html)) !== null) {
    if (m[0].startsWith('</') || m[0].startsWith('<!') || m[0].startsWith('<?')) {
      continue;
    }
    const elem = extractElementAt(html, m.index);
    if (elem && elementMatches(elem, criteria)) {
      return elem;
    }
  }

  return null;
}

/**
 * Queries all elements matching selector in the given HTML string.
 *
 * @param {string} html
 * @param {string} selector
 * @returns {Array<object>}
 */
function querySelectorAll(html, selector) {
  if (!html || !selector) return [];
  const criteria = parseToken(selector.trim());
  const results = [];

  const openTagRegex = /<([a-zA-Z0-9_-]+)(?:[\s\S]*?)>/g;
  let m;
  while ((m = openTagRegex.exec(html)) !== null) {
    if (m[0].startsWith('</') || m[0].startsWith('<!') || m[0].startsWith('<?')) {
      continue;
    }
    const elem = extractElementAt(html, m.index);
    if (elem && elementMatches(elem, criteria)) {
      results.push(elem);
    }
  }

  return results;
}

/**
 * Convenience method to find an element by its ID attribute.
 *
 * @param {string} html
 * @param {string} id
 * @returns {object|null}
 */
function getElementById(html, id) {
  return querySelector(html, `#${id}`);
}

/**
 * Checks whether an element matching selector exists.
 *
 * @param {string} html
 * @param {string} selector
 * @returns {boolean}
 */
function hasElement(html, selector) {
  return querySelector(html, selector) !== null;
}

/**
 * Returns attribute value or null.
 *
 * @param {string} html
 * @param {string} selector
 * @param {string} attrName
 * @returns {string|boolean|null}
 */
function getAttribute(html, selector, attrName) {
  const elem = querySelector(html, selector);
  return elem ? elem.getAttribute(attrName) : null;
}

/**
 * Checks if matching element has class.
 *
 * @param {string} html
 * @param {string} selector
 * @param {string} className
 * @returns {boolean}
 */
function hasClass(html, selector, className) {
  const elem = querySelector(html, selector);
  return elem ? elem.hasClass(className) : false;
}

/**
 * Returns text content of matching element.
 *
 * @param {string} html
 * @param {string} selector
 * @returns {string}
 */
function getTextContent(html, selector) {
  const elem = querySelector(html, selector);
  return elem ? elem.textContent : '';
}

module.exports = {
  querySelector,
  querySelectorAll,
  getElementById,
  hasElement,
  getAttribute,
  hasClass,
  getTextContent,
  parseAttributes
};
