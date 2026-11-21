/**
 * css-inspector.js - CSS Rule, Media Query, and Aspect-Ratio Inspector for FastFood E2E Tests.
 */

/**
 * Strips comments from CSS string.
 */
function stripComments(css) {
  return css.replace(/\/\*[\s\S]*?\*\//g, '');
}

/**
 * Normalizes CSS selector string for matching.
 */
function normalizeSelector(sel) {
  return sel.trim().replace(/\s+/g, ' ');
}

/**
 * Parses declaration block (inside { ... }) into a map of property-value pairs.
 */
function parseDeclarations(block) {
  const props = {};
  const statements = block.split(';');
  for (const stmt of statements) {
    const colonIdx = stmt.indexOf(':');
    if (colonIdx === -1) continue;
    const key = stmt.slice(0, colonIdx).trim().toLowerCase();
    const val = stmt.slice(colonIdx + 1).trim();
    if (key) {
      props[key] = val;
    }
  }
  return props;
}

/**
 * Extracts top-level rules and @media queries from CSS.
 */
function parseCssBlocks(css) {
  const cleanCss = stripComments(css);
  const rules = [];
  const mediaQueries = [];

  let i = 0;
  const len = cleanCss.length;

  while (i < len) {
    // Find next opening brace
    const openBrace = cleanCss.indexOf('{', i);
    if (openBrace === -1) break;

    const rawPrelude = cleanCss.slice(i, openBrace);
    const prelude = rawPrelude.replace(/^[}; \r\n\t]+/, '').trim();

    if (prelude.includes('@media')) {
      // Find matching closing brace for media block
      let depth = 1;
      let j = openBrace + 1;
      while (j < len && depth > 0) {
        if (cleanCss[j] === '{') depth++;
        else if (cleanCss[j] === '}') depth--;
        j++;
      }
      const mediaMatch = prelude.match(/@media\s+([\s\S]*)$/i);
      const mediaCondition = mediaMatch ? mediaMatch[1].trim() : prelude.replace(/^@media\s*/i, '').trim();
      const mediaBody = cleanCss.slice(openBrace + 1, j - 1);
      mediaQueries.push({
        condition: mediaCondition,
        body: mediaBody,
        rules: parseCssBlocks(mediaBody).rules
      });
      i = j;
    } else if (prelude.includes('@keyframes')) {
      // Keyframes also have nested braces (from { ... } to { ... })
      let depth = 1;
      let j = openBrace + 1;
      while (j < len && depth > 0) {
        if (cleanCss[j] === '{') depth++;
        else if (cleanCss[j] === '}') depth--;
        j++;
      }
      i = j;
    } else {
      // Standard rule
      const closeBrace = cleanCss.indexOf('}', openBrace);
      if (closeBrace === -1) break;
      const body = cleanCss.slice(openBrace + 1, closeBrace);
      const selectors = prelude.split(',').map(normalizeSelector);
      const declarations = parseDeclarations(body);

      rules.push({
        selectors,
        body,
        declarations
      });
      i = closeBrace + 1;
    }
  }

  return { rules, mediaQueries };
}

/**
 * Finds all rules matching selector in CSS string.
 *
 * @param {string} css
 * @param {string} targetSelector - E.g. '.ff-msg-image'
 * @returns {Array<{ selectors: string[], declarations: object, body: string }>}
 */
function findRules(css, targetSelector) {
  const { rules } = parseCssBlocks(css);
  const target = normalizeSelector(targetSelector);
  return rules.filter(r => r.selectors.some(s => s === target || s.includes(target)));
}

/**
 * Returns property value for a selector in root CSS.
 *
 * @param {string} css
 * @param {string} selector
 * @param {string} propertyName - E.g. 'object-fit', 'aspect-ratio'
 * @returns {string|null}
 */
function getProperty(css, selector, propertyName) {
  const matchingRules = findRules(css, selector);
  const prop = propertyName.toLowerCase().trim();
  for (let i = matchingRules.length - 1; i >= 0; i--) {
    if (matchingRules[i].declarations[prop] !== undefined) {
      return matchingRules[i].declarations[prop];
    }
  }
  return null;
}

/**
 * Checks whether selector has specified property, optionally verifying expected value.
 *
 * @param {string} css
 * @param {string} selector
 * @param {string} propertyName
 * @param {string} [expectedValue]
 * @returns {boolean}
 */
function hasProperty(css, selector, propertyName, expectedValue = null) {
  const val = getProperty(css, selector, propertyName);
  if (val === null) return false;
  if (expectedValue === null) return true;
  return val.toLowerCase().includes(expectedValue.toLowerCase());
}

/**
 * Finds media queries matching condition pattern.
 *
 * @param {string} css
 * @param {string|RegExp} pattern - E.g. 'max-width: 640px' or /max-width:\s*640px/
 * @returns {Array<{ condition: string, body: string, rules: any[] }>}
 */
function findMediaQueries(css, pattern) {
  const { mediaQueries } = parseCssBlocks(css);
  return mediaQueries.filter(mq => {
    if (pattern instanceof RegExp) {
      return pattern.test(mq.condition);
    }
    return mq.condition.replace(/\s+/g, '').includes(pattern.replace(/\s+/g, ''));
  });
}

/**
 * Retrieves property value defined within a specific media query.
 *
 * @param {string} css
 * @param {string|RegExp} mediaPattern - E.g. 'max-width: 640px'
 * @param {string} selector - E.g. '.ff-lightbox-close'
 * @param {string} propertyName - E.g. 'position'
 * @returns {string|null}
 */
function getPropertyInMediaQuery(css, mediaPattern, selector, propertyName) {
  const mqs = findMediaQueries(css, mediaPattern);
  const target = normalizeSelector(selector);
  const prop = propertyName.toLowerCase().trim();

  for (const mq of mqs) {
    for (let i = mq.rules.length - 1; i >= 0; i--) {
      const rule = mq.rules[i];
      if (rule.selectors.some(s => s === target || s.includes(target))) {
        if (rule.declarations[prop] !== undefined) {
          return rule.declarations[prop];
        }
      }
    }
  }

  return null;
}

/**
 * Extracts all inline <style> contents from HTML string and concatenates them.
 *
 * @param {string} html
 * @returns {string} Combined CSS
 */
function extractStylesFromHtml(html) {
  const styleRegex = /<style[^>]*>([\s\S]*?)<\/style>/gi;
  let css = '';
  let m;
  while ((m = styleRegex.exec(html)) !== null) {
    css += '\n' + m[1];
  }
  return css;
}

module.exports = {
  stripComments,
  parseCssBlocks,
  findRules,
  getProperty,
  hasProperty,
  findMediaQueries,
  getPropertyInMediaQuery,
  extractStylesFromHtml
};
