/* @ds-bundle: {"format":4,"namespace":"SimpleWebDesignSystem_2e6a6c","components":[{"name":"Card","sourcePath":"components/cards/Card.jsx"},{"name":"CourseCard","sourcePath":"components/cards/CourseCard.jsx"},{"name":"PlanCard","sourcePath":"components/cards/PlanCard.jsx"},{"name":"Accordion","sourcePath":"components/content/Accordion.jsx"},{"name":"CheckList","sourcePath":"components/content/CheckList.jsx"},{"name":"PriceTable","sourcePath":"components/content/PriceTable.jsx"},{"name":"ReasonItem","sourcePath":"components/content/ReasonItem.jsx"},{"name":"Steps","sourcePath":"components/content/Steps.jsx"},{"name":"ArrowLink","sourcePath":"components/core/ArrowLink.jsx"},{"name":"Avatar","sourcePath":"components/core/Avatar.jsx"},{"name":"Button","sourcePath":"components/core/Button.jsx"},{"name":"Notice","sourcePath":"components/core/Notice.jsx"},{"name":"NumberBadge","sourcePath":"components/core/NumberBadge.jsx"},{"name":"Pill","sourcePath":"components/core/Pill.jsx"},{"name":"Checkbox","sourcePath":"components/forms/Checkbox.jsx"},{"name":"ChoiceChips","sourcePath":"components/forms/ChoiceChips.jsx"},{"name":"Field","sourcePath":"components/forms/Field.jsx"},{"name":"Input","sourcePath":"components/forms/Input.jsx"},{"name":"Select","sourcePath":"components/forms/Select.jsx"},{"name":"Textarea","sourcePath":"components/forms/Textarea.jsx"},{"name":"Eyebrow","sourcePath":"components/layout/Eyebrow.jsx"},{"name":"MobileActionBar","sourcePath":"components/layout/MobileActionBar.jsx"},{"name":"PageHero","sourcePath":"components/layout/PageHero.jsx"},{"name":"Section","sourcePath":"components/layout/Section.jsx"},{"name":"SiteFooter","sourcePath":"components/layout/SiteFooter.jsx"},{"name":"SiteHeader","sourcePath":"components/layout/SiteHeader.jsx"},{"name":"TopBar","sourcePath":"components/layout/TopBar.jsx"}],"sourceHashes":{"components/cards/Card.jsx":"c7738331b503","components/cards/CourseCard.jsx":"b1568c5cc7e3","components/cards/PlanCard.jsx":"cb45207e2c54","components/content/Accordion.jsx":"9ea07f688aa8","components/content/CheckList.jsx":"303a9a88de3a","components/content/PriceTable.jsx":"b4c4b6d65e2a","components/content/ReasonItem.jsx":"5f0114e52ec8","components/content/Steps.jsx":"473224cad4a0","components/core/ArrowLink.jsx":"cce2be21bc29","components/core/Avatar.jsx":"ffe5e636ef6d","components/core/Button.jsx":"47b55ebb1b72","components/core/Notice.jsx":"4a15ea8523ea","components/core/NumberBadge.jsx":"3804e9f89e38","components/core/Pill.jsx":"c6514ce26a96","components/forms/Checkbox.jsx":"b3d7d8f0489e","components/forms/ChoiceChips.jsx":"1a6b7a496ab2","components/forms/Field.jsx":"f48e3c19c39c","components/forms/Input.jsx":"5677599dfbc0","components/forms/Select.jsx":"1ee74deb5858","components/forms/Textarea.jsx":"5e0b5de68437","components/layout/Eyebrow.jsx":"40cc94611618","components/layout/MobileActionBar.jsx":"9a5fc626b067","components/layout/PageHero.jsx":"16fb92030154","components/layout/Section.jsx":"b0958b894c11","components/layout/SiteFooter.jsx":"06cd9ec49727","components/layout/SiteHeader.jsx":"9145295e04fd","components/layout/TopBar.jsx":"a017e9877663","ui_kits/website/Contact.jsx":"1b8e36bac4f7","ui_kits/website/CourseB.jsx":"fe756f9db0c4","ui_kits/website/Home.jsx":"c1e077585c4f","ui_kits/website/Pricing.jsx":"165838cbc9c9","ui_kits/website/Shell.jsx":"a95516eb7854","ui_kits/website/Signup.jsx":"46c7a1fd90a9"},"inlinedExternals":[],"unexposedExports":[]} */

(() => {

const __ds_ns = (window.SimpleWebDesignSystem_2e6a6c = window.SimpleWebDesignSystem_2e6a6c || {});

const __ds_scope = {};

(__ds_ns.__errors = __ds_ns.__errors || []);

// components/cards/Card.jsx
try { (() => {
const T = {
  white: {
    bg: 'var(--surface-card)',
    fg: 'var(--text-body)',
    sh: 'var(--shadow-card)'
  },
  muted: {
    bg: 'var(--surface-muted)',
    fg: 'var(--text-body)',
    sh: 'none'
  },
  tint: {
    bg: 'var(--surface-tint)',
    fg: 'var(--text-body)',
    sh: 'none'
  },
  dark: {
    bg: 'var(--surface-dark)',
    fg: '#fff',
    sh: 'none'
  },
  primary: {
    bg: 'var(--brand-primary)',
    fg: '#fff',
    sh: 'none'
  },
  accent: {
    bg: 'var(--brand-accent)',
    fg: 'var(--text-on-accent)',
    sh: 'none'
  }
};
function Card({
  tone = 'white',
  padding = 28,
  radius = 'lg',
  gap = 12,
  href,
  children,
  style
}) {
  const [h, setH] = React.useState(false);
  const t = T[tone] || T.white;
  const st = {
    background: t.bg,
    color: t.fg,
    borderRadius: `var(--radius-${radius})`,
    boxShadow: href && h ? tone === 'white' ? 'var(--shadow-card-hover)' : 'var(--shadow-tile-hover)' : t.sh,
    padding,
    display: 'flex',
    flexDirection: 'column',
    gap,
    textDecoration: 'none',
    boxSizing: 'border-box',
    ...style
  };
  return href ? /*#__PURE__*/React.createElement("a", {
    href: href,
    style: st,
    onMouseEnter: () => setH(true),
    onMouseLeave: () => setH(false)
  }, children) : /*#__PURE__*/React.createElement("div", {
    style: st
  }, children);
}
Object.assign(__ds_scope, { Card });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/cards/Card.jsx", error: String((e && e.message) || e) }); }

// components/cards/CourseCard.jsx
try { (() => {
function CourseCard({
  name,
  price,
  desc,
  href = '#',
  cta = 'Více o kurzu'
}) {
  return /*#__PURE__*/React.createElement(__ds_scope.Card, {
    href: href,
    padding: 26,
    gap: 12
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      justifyContent: 'space-between',
      alignItems: 'baseline',
      gap: 12,
      flexWrap: 'wrap'
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      font: '900 20px var(--font-display)',
      color: 'var(--text-body)'
    }
  }, name), price && /*#__PURE__*/React.createElement("span", {
    style: {
      font: '900 18px var(--font-display)',
      color: 'var(--brand-primary)',
      whiteSpace: 'nowrap'
    }
  }, price)), desc && /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 15,
      lineHeight: 1.55,
      color: 'var(--text-muted)',
      textWrap: 'pretty'
    }
  }, desc), /*#__PURE__*/React.createElement("span", {
    style: {
      marginTop: 'auto',
      fontWeight: 800,
      fontSize: 15,
      color: 'var(--brand-primary)'
    }
  }, cta, " \u2192"));
}
Object.assign(__ds_scope, { CourseCard });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/cards/CourseCard.jsx", error: String((e && e.message) || e) }); }

// components/content/Accordion.jsx
try { (() => {
function Accordion({
  items = [],
  defaultOpen = 0
}) {
  const [open, setOpen] = React.useState(defaultOpen);
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 12
    }
  }, items.map((it, i) => {
    const o = open === i;
    return /*#__PURE__*/React.createElement("div", {
      key: i,
      style: {
        background: '#fff',
        borderRadius: 'var(--radius-md)',
        boxShadow: '0 2px 12px rgba(35,35,35,.05)',
        overflow: 'hidden'
      }
    }, /*#__PURE__*/React.createElement("button", {
      onClick: () => setOpen(o ? -1 : i),
      style: {
        width: '100%',
        cursor: 'pointer',
        background: 'transparent',
        border: 0,
        padding: '20px 24px',
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'center',
        gap: 16,
        textAlign: 'left',
        font: '800 17px/1.35 var(--font-display)',
        color: 'var(--text-body)'
      }
    }, /*#__PURE__*/React.createElement("span", null, it.q), /*#__PURE__*/React.createElement("span", {
      style: {
        flex: 'none',
        width: 32,
        height: 32,
        borderRadius: '50%',
        background: o ? 'var(--brand-primary)' : 'var(--brand-primary-tint)',
        color: o ? '#fff' : 'var(--brand-primary)',
        display: 'grid',
        placeItems: 'center',
        fontSize: 20,
        fontWeight: 700,
        fontFamily: 'var(--font-body)'
      }
    }, o ? '−' : '+')), o && /*#__PURE__*/React.createElement("div", {
      style: {
        margin: 0,
        padding: '0 24px 22px',
        fontSize: 16,
        lineHeight: 1.6,
        color: 'var(--text-soft)',
        textWrap: 'pretty'
      }
    }, it.a));
  }));
}
Object.assign(__ds_scope, { Accordion });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/content/Accordion.jsx", error: String((e && e.message) || e) }); }

// components/content/CheckList.jsx
try { (() => {
function CheckList({
  items = [],
  tone = 'primary',
  gap = 10,
  size = 16
}) {
  const c = tone === 'accent' ? 'var(--brand-accent-hover)' : 'var(--brand-primary)';
  return /*#__PURE__*/React.createElement("ul", {
    style: {
      margin: 0,
      padding: 0,
      listStyle: 'none',
      display: 'flex',
      flexDirection: 'column',
      gap,
      fontSize: size,
      lineHeight: 1.45
    }
  }, items.map((it, i) => /*#__PURE__*/React.createElement("li", {
    key: i,
    style: {
      display: 'flex',
      gap: 10
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      color: c,
      fontWeight: 900
    }
  }, "\u2713"), /*#__PURE__*/React.createElement("span", null, it))));
}
Object.assign(__ds_scope, { CheckList });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/content/CheckList.jsx", error: String((e && e.message) || e) }); }

// components/content/PriceTable.jsx
try { (() => {
function PriceTable({
  title,
  intro,
  columns,
  rows = [],
  card = true,
  size = 16
}) {
  const n = (columns || rows[0] || []).length;
  const tpl = 'minmax(0,1fr)' + ' auto'.repeat(Math.max(n - 1, 1));
  const row = (cells, i, last, head) => /*#__PURE__*/React.createElement("div", {
    key: i,
    style: {
      display: 'grid',
      gridTemplateColumns: tpl,
      gap: 16,
      padding: head ? '10px 0' : '12px 0',
      borderBottom: last ? 0 : '1px solid var(--border-divider)',
      fontSize: head ? 13 : size,
      fontWeight: head ? 800 : 400,
      color: head ? 'var(--text-muted)' : 'var(--text-body)'
    }
  }, cells.map((c, j) => head ? /*#__PURE__*/React.createElement("span", {
    key: j
  }, c) : j === 1 ? /*#__PURE__*/React.createElement("strong", {
    key: j
  }, c) : /*#__PURE__*/React.createElement("span", {
    key: j,
    style: j > 1 ? {
      color: 'var(--text-muted)'
    } : null
  }, c)));
  const body = /*#__PURE__*/React.createElement(React.Fragment, null, title && /*#__PURE__*/React.createElement("h2", {
    style: {
      margin: '0 0 8px',
      font: 'var(--type-card-title)',
      textTransform: 'uppercase'
    }
  }, title), intro && /*#__PURE__*/React.createElement("p", {
    style: {
      margin: '0 0 6px',
      fontSize: 15,
      color: 'var(--text-muted)',
      lineHeight: 1.55
    }
  }, intro), columns && row(columns, 'h', false, true), rows.map((r, i) => row(r, i, i === rows.length - 1)));
  return card ? /*#__PURE__*/React.createElement("div", {
    style: {
      background: '#fff',
      borderRadius: 'var(--radius-lg)',
      boxShadow: 'var(--shadow-card)',
      padding: 'clamp(22px,4vw,32px)',
      display: 'flex',
      flexDirection: 'column',
      gap: 6
    }
  }, body) : /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column'
    }
  }, body);
}
Object.assign(__ds_scope, { PriceTable });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/content/PriceTable.jsx", error: String((e && e.message) || e) }); }

// components/core/ArrowLink.jsx
try { (() => {
function ArrowLink({
  href = '#',
  children,
  back = false,
  tone = 'primary',
  size = 16
}) {
  const [h, setH] = React.useState(false);
  const c = tone === 'accent' ? 'var(--brand-accent)' : h ? 'var(--text-link-hover)' : 'var(--text-link)';
  return /*#__PURE__*/React.createElement("a", {
    href: href,
    onMouseEnter: () => setH(true),
    onMouseLeave: () => setH(false),
    style: {
      color: c,
      fontWeight: 800,
      fontSize: size,
      textDecoration: 'none'
    }
  }, back ? '← ' : '', children, back ? '' : ' →');
}
Object.assign(__ds_scope, { ArrowLink });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/ArrowLink.jsx", error: String((e && e.message) || e) }); }

// components/core/Avatar.jsx
try { (() => {
function Avatar({
  initial,
  src,
  alt = '',
  size = 120,
  tone = 'primary',
  ring = false
}) {
  const t = tone === 'accent' ? {
    bg: 'var(--brand-accent-tint)',
    fg: 'var(--brand-accent-strong)'
  } : {
    bg: 'var(--brand-primary-tint)',
    fg: 'var(--brand-primary)'
  };
  const base = {
    width: size,
    height: size,
    borderRadius: '50%',
    flex: 'none',
    border: ring ? '2px solid var(--brand-accent)' : 0,
    boxSizing: 'border-box'
  };
  if (src) return /*#__PURE__*/React.createElement("img", {
    src: src,
    alt: alt,
    style: {
      ...base,
      objectFit: 'cover',
      objectPosition: '50% 18%',
      background: t.bg
    }
  });
  return /*#__PURE__*/React.createElement("div", {
    style: {
      ...base,
      background: t.bg,
      color: t.fg,
      display: 'grid',
      placeItems: 'center',
      font: `900 ${Math.round(size / 3)}px var(--font-display)`
    }
  }, initial);
}
Object.assign(__ds_scope, { Avatar });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/Avatar.jsx", error: String((e && e.message) || e) }); }

// components/core/Button.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
const V = {
  accent: {
    bg: 'var(--brand-accent)',
    fg: 'var(--text-on-accent)',
    hbg: 'var(--brand-accent-hover)',
    hfg: 'var(--text-on-accent)'
  },
  primary: {
    bg: 'var(--brand-primary)',
    fg: 'var(--text-on-primary)',
    hbg: 'var(--brand-primary-hover)',
    hfg: 'var(--text-on-primary)'
  },
  secondary: {
    bg: '#fff',
    fg: 'var(--text-body)',
    hbg: '#fff',
    hfg: 'var(--brand-primary)',
    shadow: 'var(--shadow-sm)'
  },
  outline: {
    bg: 'transparent',
    fg: 'var(--text-body)',
    hbg: 'transparent',
    hfg: 'var(--brand-primary)',
    border: '1.5px solid var(--border-input)'
  },
  dark: {
    bg: 'var(--neutral-900)',
    fg: '#fff',
    hbg: '#000',
    hfg: '#fff'
  },
  muted: {
    bg: 'var(--surface-muted)',
    fg: 'var(--text-body)',
    hbg: 'var(--neutral-100)',
    hfg: 'var(--text-body)'
  }
};
const S = {
  sm: {
    fs: 14,
    pad: '11px 20px'
  },
  md: {
    fs: 15,
    pad: '12px 22px'
  },
  lg: {
    fs: 16,
    pad: '16px 28px'
  },
  xl: {
    fs: 17,
    pad: '17px 32px'
  }
};
function Button({
  variant = 'accent',
  size = 'lg',
  href,
  block = false,
  glow = false,
  disabled = false,
  type = 'button',
  onClick,
  children,
  style
}) {
  const [h, setH] = React.useState(false);
  const v = V[variant] || V.accent,
    s = S[size] || S.lg;
  const st = {
    display: block ? 'flex' : 'inline-flex',
    width: block ? '100%' : undefined,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
    textAlign: 'center',
    cursor: disabled ? 'not-allowed' : 'pointer',
    opacity: disabled ? .5 : 1,
    background: h && !disabled ? v.hbg : v.bg,
    color: h && !disabled ? v.hfg : v.fg,
    border: v.border || 0,
    borderRadius: 'var(--radius-pill)',
    fontFamily: 'var(--font-body)',
    fontWeight: 800,
    fontSize: s.fs,
    lineHeight: 1.2,
    padding: s.pad,
    boxShadow: glow ? 'var(--shadow-cta)' : v.shadow || 'none',
    textDecoration: 'none',
    boxSizing: 'border-box',
    ...style
  };
  const ev = {
    onMouseEnter: () => setH(true),
    onMouseLeave: () => setH(false),
    onClick
  };
  return href && !disabled ? /*#__PURE__*/React.createElement("a", _extends({
    href: href,
    style: st
  }, ev), children) : /*#__PURE__*/React.createElement("button", _extends({
    type: type,
    disabled: disabled,
    style: st
  }, ev), children);
}
Object.assign(__ds_scope, { Button });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/Button.jsx", error: String((e && e.message) || e) }); }

// components/cards/PlanCard.jsx
try { (() => {
function PlanCard({
  eyebrow,
  title,
  price,
  priceNote,
  features = [],
  ctaLabel = 'Přihlásit se',
  href = '#',
  tone = 'primary'
}) {
  const acc = tone === 'accent';
  return /*#__PURE__*/React.createElement("article", {
    style: {
      background: '#fff',
      borderRadius: 'var(--radius-lg)',
      boxShadow: 'var(--shadow-card)',
      overflow: 'hidden',
      display: 'flex',
      flexDirection: 'column'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      background: acc ? 'var(--brand-accent)' : 'var(--brand-primary)',
      color: acc ? 'var(--text-on-accent)' : '#fff',
      padding: '26px 30px',
      display: 'flex',
      flexDirection: 'column',
      gap: 6
    }
  }, eyebrow && /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 13,
      fontWeight: 800,
      letterSpacing: '1.5px',
      textTransform: 'uppercase',
      opacity: acc ? .8 : .85
    }
  }, eyebrow), /*#__PURE__*/React.createElement("h2", {
    style: {
      margin: 0,
      font: '900 28px var(--font-display)',
      textTransform: 'uppercase'
    }
  }, title)), /*#__PURE__*/React.createElement("div", {
    style: {
      padding: '28px 30px',
      display: 'flex',
      flexDirection: 'column',
      gap: 22,
      flex: 1
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 4
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      font: 'var(--type-price-xl)'
    }
  }, price), priceNote && /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 14,
      color: 'var(--text-muted)'
    }
  }, priceNote)), /*#__PURE__*/React.createElement("ul", {
    style: {
      margin: 0,
      padding: 0,
      listStyle: 'none',
      display: 'flex',
      flexDirection: 'column',
      gap: 12,
      fontSize: 16,
      lineHeight: 1.45
    }
  }, features.map((f, i) => /*#__PURE__*/React.createElement("li", {
    key: i,
    style: {
      display: 'flex',
      gap: 10
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      color: acc ? 'var(--brand-accent-hover)' : 'var(--brand-primary)',
      fontWeight: 900
    }
  }, "\u2713"), /*#__PURE__*/React.createElement("span", null, f)))), /*#__PURE__*/React.createElement("div", {
    style: {
      marginTop: 'auto'
    }
  }, /*#__PURE__*/React.createElement(__ds_scope.Button, {
    variant: acc ? 'accent' : 'primary',
    block: true,
    href: href
  }, ctaLabel))));
}
Object.assign(__ds_scope, { PlanCard });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/cards/PlanCard.jsx", error: String((e && e.message) || e) }); }

// components/core/Notice.jsx
try { (() => {
function Notice({
  children,
  tone = 'accent'
}) {
  const t = tone === 'info' ? {
    bg: 'var(--brand-primary-tint)',
    bd: 'var(--brand-primary)'
  } : tone === 'danger' ? {
    bg: '#FCEDEA',
    bd: 'var(--danger)'
  } : {
    bg: 'var(--brand-accent-tint)',
    bd: 'var(--brand-accent)'
  };
  return /*#__PURE__*/React.createElement("div", {
    style: {
      background: t.bg,
      border: `1.5px solid ${t.bd}`,
      borderRadius: 'var(--radius-md)',
      padding: '16px 20px',
      fontSize: 16,
      lineHeight: 1.5,
      color: 'var(--text-body)'
    }
  }, children);
}
Object.assign(__ds_scope, { Notice });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/Notice.jsx", error: String((e && e.message) || e) }); }

// components/core/NumberBadge.jsx
try { (() => {
function NumberBadge({
  n,
  size = 'sm',
  tone = 'default'
}) {
  const d = size === 'lg' ? 56 : 44,
    fs = size === 'lg' ? 22 : 18;
  const acc = tone === 'accent';
  return /*#__PURE__*/React.createElement("span", {
    style: {
      width: d,
      height: d,
      flex: 'none',
      borderRadius: '50%',
      background: acc ? 'var(--brand-accent)' : '#fff',
      color: acc ? 'var(--text-on-accent)' : 'var(--brand-primary)',
      display: 'grid',
      placeItems: 'center',
      font: `900 ${fs}px var(--font-display)`,
      boxShadow: acc ? 'none' : size === 'lg' ? '0 2px 10px rgba(35,35,35,.08)' : '0 2px 8px rgba(35,35,35,.08)'
    }
  }, n);
}
Object.assign(__ds_scope, { NumberBadge });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/NumberBadge.jsx", error: String((e && e.message) || e) }); }

// components/content/ReasonItem.jsx
try { (() => {
function ReasonItem({
  n,
  title,
  text
}) {
  return /*#__PURE__*/React.createElement("div", {
    style: {
      background: 'var(--surface-muted)',
      borderRadius: 'var(--radius-lg)',
      padding: 26,
      display: 'flex',
      gap: 16,
      alignItems: 'flex-start'
    }
  }, /*#__PURE__*/React.createElement(__ds_scope.NumberBadge, {
    n: n
  }), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 6
    }
  }, /*#__PURE__*/React.createElement("strong", {
    style: {
      font: 'var(--type-item-title)'
    }
  }, title), /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 15,
      lineHeight: 1.55,
      color: 'var(--text-muted)',
      textWrap: 'pretty'
    }
  }, text)));
}
Object.assign(__ds_scope, { ReasonItem });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/content/ReasonItem.jsx", error: String((e && e.message) || e) }); }

// components/content/Steps.jsx
try { (() => {
function Steps({
  items = [],
  highlightLast = true,
  min = 230
}) {
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gridTemplateColumns: `repeat(auto-fit,minmax(min(100%,${min}px),1fr))`,
      gap: 24
    }
  }, items.map((s, i) => /*#__PURE__*/React.createElement("div", {
    key: i,
    style: {
      display: 'flex',
      flexDirection: 'column',
      alignItems: 'center',
      textAlign: 'center',
      gap: 10,
      padding: 8
    }
  }, /*#__PURE__*/React.createElement(__ds_scope.NumberBadge, {
    n: i + 1,
    size: "lg",
    tone: highlightLast && i === items.length - 1 ? 'accent' : 'default'
  }), /*#__PURE__*/React.createElement("strong", {
    style: {
      fontSize: 18
    }
  }, s.title), /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 15,
      lineHeight: 1.5,
      color: 'var(--text-muted)',
      textWrap: 'pretty'
    }
  }, s.text))));
}
Object.assign(__ds_scope, { Steps });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/content/Steps.jsx", error: String((e && e.message) || e) }); }

// components/core/Pill.jsx
try { (() => {
function Pill({
  children,
  tone = 'white',
  style
}) {
  const t = {
    white: {
      bg: '#fff',
      fg: 'var(--text-body)',
      sh: 'var(--shadow-xs)'
    },
    muted: {
      bg: 'var(--surface-muted)',
      fg: 'var(--text-body)',
      sh: 'none'
    },
    tint: {
      bg: 'var(--surface-tint)',
      fg: 'var(--brand-primary)',
      sh: 'none'
    },
    accent: {
      bg: 'var(--brand-accent-tint)',
      fg: 'var(--brand-accent-strong)',
      sh: 'none'
    }
  }[tone] || {};
  return /*#__PURE__*/React.createElement("span", {
    style: {
      display: 'inline-flex',
      alignItems: 'center',
      fontSize: 14,
      fontWeight: 700,
      lineHeight: 1.3,
      background: t.bg,
      color: t.fg,
      padding: '7px 14px',
      borderRadius: 'var(--radius-pill)',
      boxShadow: t.sh,
      ...style
    }
  }, children);
}
Object.assign(__ds_scope, { Pill });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/core/Pill.jsx", error: String((e && e.message) || e) }); }

// components/forms/Checkbox.jsx
try { (() => {
function Checkbox({
  checked,
  onChange,
  children
}) {
  return /*#__PURE__*/React.createElement("label", {
    style: {
      display: 'flex',
      gap: 10,
      alignItems: 'flex-start',
      fontSize: 14,
      lineHeight: 1.45,
      cursor: 'pointer',
      color: 'var(--text-muted)'
    }
  }, /*#__PURE__*/React.createElement("input", {
    type: "checkbox",
    checked: checked,
    onChange: onChange,
    style: {
      width: 20,
      height: 20,
      accentColor: 'var(--brand-primary)',
      margin: 0,
      flex: 'none'
    }
  }), /*#__PURE__*/React.createElement("span", null, children));
}
Object.assign(__ds_scope, { Checkbox });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/Checkbox.jsx", error: String((e && e.message) || e) }); }

// components/forms/ChoiceChips.jsx
try { (() => {
function ChoiceChips({
  options = [],
  value,
  onChange,
  tone = 'primary'
}) {
  const sel = tone === 'accent' ? 'var(--brand-accent)' : 'var(--brand-primary)';
  const selFg = tone === 'accent' ? 'var(--text-on-accent)' : '#fff';
  return /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexWrap: 'wrap',
      gap: 8
    }
  }, options.map(o => {
    const on = o === value;
    return /*#__PURE__*/React.createElement("button", {
      key: o,
      type: "button",
      onClick: () => onChange && onChange(o),
      style: {
        cursor: 'pointer',
        border: `1.5px solid ${on ? sel : 'var(--border-input)'}`,
        borderRadius: 'var(--radius-pill)',
        padding: '9px 16px',
        fontWeight: 700,
        fontSize: 14,
        background: on ? sel : '#fff',
        color: on ? selFg : 'var(--text-body)',
        fontFamily: 'inherit'
      }
    }, o);
  }));
}
Object.assign(__ds_scope, { ChoiceChips });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/ChoiceChips.jsx", error: String((e && e.message) || e) }); }

// components/forms/Field.jsx
try { (() => {
function Field({
  label,
  optional = false,
  optionalLabel = 'nepovinné',
  error,
  children
}) {
  return /*#__PURE__*/React.createElement("label", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 6,
      fontWeight: 800,
      fontSize: 15,
      color: 'var(--text-body)'
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 2
    }
  }, label, optional && /*#__PURE__*/React.createElement("span", {
    style: {
      fontWeight: 600,
      color: 'var(--text-muted)',
      fontSize: 13
    }
  }, optionalLabel)), children, error && /*#__PURE__*/React.createElement("span", {
    style: {
      color: 'var(--danger)',
      fontSize: 13,
      fontWeight: 700
    }
  }, error));
}
Object.assign(__ds_scope, { Field });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/Field.jsx", error: String((e && e.message) || e) }); }

// components/forms/Input.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
function Input({
  error = false,
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("input", _extends({}, rest, {
    style: {
      fontSize: 16,
      fontWeight: 400,
      padding: '13px 14px',
      border: `1.5px solid ${error ? 'var(--danger)' : 'var(--border-input)'}`,
      borderRadius: 'var(--radius-input)',
      background: '#fff',
      color: 'var(--text-body)',
      fontFamily: 'inherit',
      width: '100%',
      boxSizing: 'border-box',
      outlineColor: 'var(--brand-primary)',
      ...style
    }
  }));
}
Object.assign(__ds_scope, { Input });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/Input.jsx", error: String((e && e.message) || e) }); }

// components/forms/Select.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
function Select({
  error = false,
  options,
  children,
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("select", _extends({}, rest, {
    style: {
      fontSize: 16,
      fontWeight: 400,
      padding: '13px 14px',
      border: `1.5px solid ${error ? 'var(--danger)' : 'var(--border-input)'}`,
      borderRadius: 'var(--radius-input)',
      background: '#fff',
      color: 'var(--text-body)',
      fontFamily: 'inherit',
      width: '100%',
      boxSizing: 'border-box',
      outlineColor: 'var(--brand-primary)',
      fontWeight: 600,
      ...style
    }
  }), options ? options.map(o => {
    const v = typeof o === 'string' ? o : o.value,
      l = typeof o === 'string' ? o : o.label;
    return /*#__PURE__*/React.createElement("option", {
      key: v,
      value: v
    }, l);
  }) : children);
}
Object.assign(__ds_scope, { Select });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/Select.jsx", error: String((e && e.message) || e) }); }

// components/forms/Textarea.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
function Textarea({
  error = false,
  style,
  ...rest
}) {
  return /*#__PURE__*/React.createElement("textarea", _extends({}, rest, {
    style: {
      fontSize: 16,
      fontWeight: 400,
      padding: '13px 14px',
      border: `1.5px solid ${error ? 'var(--danger)' : 'var(--border-input)'}`,
      borderRadius: 'var(--radius-input)',
      background: '#fff',
      color: 'var(--text-body)',
      fontFamily: 'inherit',
      width: '100%',
      boxSizing: 'border-box',
      outlineColor: 'var(--brand-primary)',
      resize: 'vertical',
      ...style
    }
  }));
}
Object.assign(__ds_scope, { Textarea });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/forms/Textarea.jsx", error: String((e && e.message) || e) }); }

// components/layout/Eyebrow.jsx
try { (() => {
function Eyebrow({
  children,
  tone = 'primary'
}) {
  return /*#__PURE__*/React.createElement("h3", {
    style: {
      margin: 0,
      font: '800 15px var(--font-display)',
      letterSpacing: '1.5px',
      textTransform: 'uppercase',
      color: tone === 'primary' ? 'var(--brand-primary)' : tone === 'accent' ? 'var(--brand-accent)' : 'inherit'
    }
  }, children);
}
Object.assign(__ds_scope, { Eyebrow });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/layout/Eyebrow.jsx", error: String((e && e.message) || e) }); }

// components/layout/MobileActionBar.jsx
try { (() => {
function MobileActionBar({
  callLabel = 'Zavolat',
  callHref = '#',
  ctaLabel = 'Přihlásit se',
  ctaHref = '#',
  fixed = true
}) {
  const b = {
    textAlign: 'center',
    fontWeight: 800,
    fontSize: 16,
    padding: 14,
    borderRadius: 'var(--radius-pill)',
    textDecoration: 'none'
  };
  return /*#__PURE__*/React.createElement("div", {
    style: {
      position: fixed ? 'fixed' : 'relative',
      left: 0,
      right: 0,
      bottom: 0,
      zIndex: 20,
      background: '#fff',
      boxShadow: 'var(--shadow-bar)',
      padding: '10px 12px',
      display: 'grid',
      gridTemplateColumns: '1fr 1fr',
      gap: 10
    }
  }, /*#__PURE__*/React.createElement("a", {
    href: callHref,
    style: {
      ...b,
      background: 'var(--neutral-900)',
      color: '#fff'
    }
  }, callLabel), /*#__PURE__*/React.createElement("a", {
    href: ctaHref,
    style: {
      ...b,
      background: 'var(--brand-accent)',
      color: 'var(--text-on-accent)'
    }
  }, ctaLabel));
}
Object.assign(__ds_scope, { MobileActionBar });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/layout/MobileActionBar.jsx", error: String((e && e.message) || e) }); }

// components/layout/PageHero.jsx
try { (() => {
function PageHero({
  title,
  lead,
  back,
  backHref = '#',
  variant = 'gradient',
  children,
  width = 'var(--container)'
}) {
  const dark = variant === 'dark';
  return /*#__PURE__*/React.createElement("section", {
    style: {
      background: dark ? 'var(--surface-dark)' : 'var(--gradient-hero)',
      color: dark ? '#fff' : 'var(--text-body)'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      maxWidth: width,
      margin: '0 auto',
      padding: back ? '48px 24px 40px' : '56px 24px 40px',
      display: 'flex',
      flexDirection: 'column',
      gap: 16
    }
  }, back && /*#__PURE__*/React.createElement("a", {
    href: backHref,
    style: {
      fontSize: 14,
      fontWeight: 800
    }
  }, "\u2190 ", back), /*#__PURE__*/React.createElement("h1", {
    style: {
      margin: 0,
      font: '900 clamp(36px,5vw,58px)/1.12 var(--font-display)',
      textTransform: 'uppercase',
      textWrap: 'balance'
    }
  }, title), lead && /*#__PURE__*/React.createElement("p", {
    style: {
      margin: 0,
      fontSize: 18,
      lineHeight: 1.6,
      color: dark ? 'rgba(255,255,255,.85)' : 'var(--text-muted)',
      maxWidth: 680,
      textWrap: 'pretty'
    }
  }, lead), children));
}
Object.assign(__ds_scope, { PageHero });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/layout/PageHero.jsx", error: String((e && e.message) || e) }); }

// components/layout/Section.jsx
try { (() => {
function Section({
  title,
  align = 'center',
  tone = 'white',
  width = 'var(--container)',
  gap = 40,
  padTop = 80,
  padBottom = 80,
  children
}) {
  const inner = /*#__PURE__*/React.createElement("div", {
    style: {
      maxWidth: width,
      margin: '0 auto',
      padding: `${padTop}px 24px ${padBottom}px`,
      display: 'flex',
      flexDirection: 'column',
      gap,
      boxSizing: 'border-box'
    }
  }, title && /*#__PURE__*/React.createElement("h2", {
    style: {
      margin: 0,
      textAlign: align,
      font: 'var(--type-h2)',
      textTransform: 'uppercase',
      textWrap: 'balance'
    }
  }, title), children);
  return /*#__PURE__*/React.createElement("section", {
    style: {
      background: tone === 'muted' ? 'var(--surface-muted)' : tone === 'dark' ? 'var(--surface-dark)' : 'transparent',
      color: tone === 'dark' ? '#fff' : undefined
    }
  }, inner);
}
Object.assign(__ds_scope, { Section });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/layout/Section.jsx", error: String((e && e.message) || e) }); }

// components/layout/SiteFooter.jsx
try { (() => {
function FL({
  href,
  children,
  style
}) {
  const [h, setH] = React.useState(false);
  return /*#__PURE__*/React.createElement("a", {
    href: href,
    onMouseEnter: () => setH(true),
    onMouseLeave: () => setH(false),
    style: {
      color: h ? 'var(--brand-accent)' : '#fff',
      ...style
    }
  }, children);
}
function Social({
  s
}) {
  const [h, setH] = React.useState(false);
  return /*#__PURE__*/React.createElement("a", {
    href: s.href || '#',
    "aria-label": s.label,
    title: s.label,
    onMouseEnter: () => setH(true),
    onMouseLeave: () => setH(false),
    style: {
      width: 42,
      height: 42,
      borderRadius: '50%',
      background: h ? 'var(--brand-accent)' : 'rgba(255,255,255,.1)',
      display: 'grid',
      placeItems: 'center'
    }
  }, /*#__PURE__*/React.createElement("img", {
    src: s.icon,
    alt: "",
    width: "20",
    height: "20",
    style: {
      filter: h ? 'none' : 'invert(1)'
    }
  }));
}
function SiteFooter({
  brandPrefix = 'U',
  brandName = 'Bouráka',
  tagline,
  socialsTitle = 'Sledujte nás',
  socials = [],
  columns = [],
  legalTitle,
  legal = [],
  copyright,
  bottomLinks = []
}) {
  return /*#__PURE__*/React.createElement("footer", {
    style: {
      background: 'var(--surface-footer)',
      color: '#fff'
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      maxWidth: 'var(--container)',
      margin: '0 auto',
      padding: '48px 24px 28px',
      display: 'flex',
      flexDirection: 'column',
      gap: 32
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'grid',
      gridTemplateColumns: 'repeat(auto-fit,minmax(min(100%,210px),1fr))',
      gap: 28,
      fontSize: 15,
      lineHeight: 1.8
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      gap: 6
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      font: '900 20px var(--font-display)',
      textTransform: 'uppercase'
    }
  }, brandPrefix && /*#__PURE__*/React.createElement("span", {
    style: {
      color: 'var(--brand-accent)'
    }
  }, brandPrefix, " "), brandName), tagline && /*#__PURE__*/React.createElement("span", {
    style: {
      opacity: .75,
      lineHeight: 1.5
    }
  }, tagline), socials.length > 0 && /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("strong", {
    style: {
      color: 'var(--brand-accent)',
      marginTop: 10
    }
  }, socialsTitle), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: 10
    }
  }, socials.map(s => /*#__PURE__*/React.createElement(Social, {
    key: s.label,
    s: s
  }))))), columns.map(c => /*#__PURE__*/React.createElement("div", {
    key: c.title,
    style: {
      display: 'flex',
      flexDirection: 'column'
    }
  }, /*#__PURE__*/React.createElement("strong", {
    style: {
      color: 'var(--brand-accent)'
    }
  }, c.title), c.links.map((l, i) => l.href ? /*#__PURE__*/React.createElement(FL, {
    key: i,
    href: l.href
  }, l.label) : /*#__PURE__*/React.createElement("span", {
    key: i,
    style: l.muted ? {
      opacity: .7,
      fontSize: 14,
      lineHeight: 1.5
    } : null
  }, l.label)))), legal.length > 0 && /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      fontSize: 14,
      lineHeight: 1.6,
      gap: 6
    }
  }, /*#__PURE__*/React.createElement("strong", {
    style: {
      color: 'var(--brand-accent)',
      fontSize: 15
    }
  }, legalTitle), legal.map((t, i) => /*#__PURE__*/React.createElement("span", {
    key: i,
    style: {
      opacity: .8
    }
  }, t)))), /*#__PURE__*/React.createElement("div", {
    style: {
      borderTop: '1px solid var(--border-on-dark)',
      paddingTop: 18,
      fontSize: 13,
      display: 'flex',
      justifyContent: 'space-between',
      gap: 12,
      flexWrap: 'wrap'
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      opacity: .6
    }
  }, copyright), /*#__PURE__*/React.createElement("div", {
    style: {
      display: 'flex',
      gap: 16,
      flexWrap: 'wrap'
    }
  }, bottomLinks.map(l => /*#__PURE__*/React.createElement(FL, {
    key: l.label,
    href: l.href,
    style: {
      opacity: .75
    }
  }, l.label))))));
}
Object.assign(__ds_scope, { SiteFooter });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/layout/SiteFooter.jsx", error: String((e && e.message) || e) }); }

// components/layout/SiteHeader.jsx
try { (() => {
function SiteHeader({
  brandPrefix = 'U',
  brandName = 'Bouráka',
  tagline = 'Autoškola',
  logoSrc,
  homeHref = '#',
  items = [],
  active,
  ctaLabel = 'Přihlásit se',
  ctaHref = '#',
  breakpoint = 900,
  extraMobileLinks = []
}) {
  const [mobile, setMobile] = React.useState(false),
    [open, setOpen] = React.useState(false);
  React.useEffect(() => {
    const r = () => setMobile(window.innerWidth < breakpoint);
    r();
    window.addEventListener('resize', r);
    return () => window.removeEventListener('resize', r);
  }, [breakpoint]);
  return /*#__PURE__*/React.createElement("header", {
    style: {
      background: '#fff',
      boxShadow: 'var(--shadow-header)',
      position: 'relative',
      zIndex: 5
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      maxWidth: 'var(--container)',
      margin: '0 auto',
      padding: '10px 24px',
      display: 'flex',
      alignItems: 'center',
      gap: 24,
      flexWrap: 'wrap'
    }
  }, /*#__PURE__*/React.createElement("a", {
    href: homeHref,
    style: {
      display: 'flex',
      alignItems: 'center',
      gap: 12,
      color: 'var(--text-body)'
    }
  }, logoSrc && /*#__PURE__*/React.createElement("img", {
    src: logoSrc,
    alt: brandPrefix + ' ' + brandName,
    style: {
      width: 56,
      height: 56,
      objectFit: 'cover',
      objectPosition: '50% 20%',
      borderRadius: '50%',
      border: '2px solid var(--brand-accent)',
      boxSizing: 'border-box'
    }
  }), /*#__PURE__*/React.createElement("span", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      lineHeight: 1.1
    }
  }, /*#__PURE__*/React.createElement("span", {
    style: {
      font: '900 20px var(--font-display)',
      textTransform: 'uppercase'
    }
  }, brandPrefix && /*#__PURE__*/React.createElement("span", {
    style: {
      color: 'var(--brand-accent)'
    }
  }, brandPrefix, " "), brandName), tagline && /*#__PURE__*/React.createElement("span", {
    style: {
      fontSize: 12,
      fontWeight: 700,
      letterSpacing: '2px',
      textTransform: 'uppercase',
      opacity: .6
    }
  }, tagline))), !mobile && /*#__PURE__*/React.createElement("nav", {
    style: {
      display: 'flex',
      gap: 22,
      marginLeft: 'auto',
      flexWrap: 'wrap',
      alignItems: 'center'
    }
  }, items.map(i => {
    const a = i.id === active;
    return /*#__PURE__*/React.createElement("a", {
      key: i.id,
      href: i.href,
      style: {
        color: a ? 'var(--brand-primary)' : 'var(--text-body)',
        fontWeight: 700,
        fontSize: 15,
        padding: '6px 0',
        borderBottom: `3px solid ${a ? 'var(--brand-accent)' : 'transparent'}`
      }
    }, i.label);
  }), ctaLabel && /*#__PURE__*/React.createElement(__ds_scope.Button, {
    variant: "primary",
    size: "md",
    href: ctaHref
  }, ctaLabel)), mobile && /*#__PURE__*/React.createElement("button", {
    onClick: () => setOpen(!open),
    style: {
      marginLeft: 'auto',
      cursor: 'pointer',
      background: 'var(--surface-muted)',
      border: 0,
      borderRadius: 'var(--radius-pill)',
      padding: '12px 20px',
      fontWeight: 800,
      fontSize: 15,
      color: 'var(--text-body)',
      fontFamily: 'inherit'
    }
  }, open ? 'Zavřít' : 'Menu')), mobile && open && /*#__PURE__*/React.createElement("nav", {
    style: {
      display: 'flex',
      flexDirection: 'column',
      padding: '8px 24px 20px',
      gap: 2,
      borderTop: '1px solid var(--border-divider)'
    }
  }, items.concat(extraMobileLinks).map(i => /*#__PURE__*/React.createElement("a", {
    key: i.id || i.label,
    href: i.href,
    onClick: () => setOpen(false),
    style: {
      color: i.id === active ? 'var(--brand-primary)' : 'var(--text-body)',
      fontWeight: 800,
      fontSize: 17,
      padding: '12px 0'
    }
  }, i.label))));
}
Object.assign(__ds_scope, { SiteHeader });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/layout/SiteHeader.jsx", error: String((e && e.message) || e) }); }

// components/layout/TopBar.jsx
try { (() => {
function TopBar({
  phone,
  email,
  hours,
  linkLabel,
  linkHref = '#'
}) {
  return /*#__PURE__*/React.createElement("div", {
    style: {
      background: 'var(--neutral-900)',
      color: '#fff',
      fontSize: 14
    }
  }, /*#__PURE__*/React.createElement("div", {
    style: {
      maxWidth: 'var(--container)',
      margin: '0 auto',
      padding: '9px 24px',
      display: 'flex',
      gap: 20,
      alignItems: 'center',
      flexWrap: 'wrap'
    }
  }, phone && /*#__PURE__*/React.createElement("a", {
    href: 'tel:' + phone.replace(/\s/g, ''),
    style: {
      color: '#fff',
      fontWeight: 700
    }
  }, "\u260E ", phone), email && /*#__PURE__*/React.createElement("a", {
    href: 'mailto:' + email,
    style: {
      color: '#fff',
      opacity: .85
    }
  }, email), hours && /*#__PURE__*/React.createElement("span", {
    style: {
      opacity: .7
    }
  }, hours), linkLabel && /*#__PURE__*/React.createElement("a", {
    href: linkHref,
    style: {
      marginLeft: 'auto',
      color: 'var(--brand-accent)',
      fontWeight: 800
    }
  }, linkLabel, " \u2192")));
}
Object.assign(__ds_scope, { TopBar });
})(); } catch (e) { __ds_ns.__errors.push({ path: "components/layout/TopBar.jsx", error: String((e && e.message) || e) }); }

// ui_kits/website/Contact.jsx
try { (() => {
(() => {
  const {
    PageHero,
    Card
  } = window.SimpleWebDesignSystem_2e6a6c;
  function Contact() {
    const lab = {
      fontSize: 13,
      fontWeight: 800,
      letterSpacing: '1.5px',
      textTransform: 'uppercase'
    };
    return /*#__PURE__*/React.createElement(Shell, {
      active: "kontakt"
    }, /*#__PURE__*/React.createElement(PageHero, {
      title: "Kontakt",
      lead: "Nejrychleji n\xE1s zastihnete po telefonu. Kdy\u017E zrovna u\u010D\xEDme, napi\u0161te n\xE1m SMS nebo zpr\xE1vu na WhatsApp a ozveme se."
    }), /*#__PURE__*/React.createElement("main", {
      style: {
        maxWidth: 1100,
        margin: '0 auto',
        padding: '16px 24px 80px',
        display: 'flex',
        flexDirection: 'column',
        gap: 28
      }
    }, /*#__PURE__*/React.createElement("div", {
      style: {
        display: 'grid',
        gridTemplateColumns: 'repeat(auto-fit,minmax(min(100%,300px),1fr))',
        gap: 20
      }
    }, ['Dominik', 'Ondřej Dvořák'].map(n => /*#__PURE__*/React.createElement(Card, {
      key: n,
      tone: "primary",
      gap: 10
    }, /*#__PURE__*/React.createElement("span", {
      style: {
        ...lab,
        opacity: .85
      }
    }, "Telefon"), /*#__PURE__*/React.createElement("strong", {
      style: {
        font: '900 22px var(--font-display)'
      }
    }, n), /*#__PURE__*/React.createElement("div", {
      style: {
        display: 'flex',
        gap: 14,
        flexWrap: 'wrap',
        fontSize: 17,
        fontWeight: 800
      }
    }, /*#__PURE__*/React.createElement("a", {
      href: "#",
      style: {
        color: '#fff'
      }
    }, PHONE), /*#__PURE__*/React.createElement("a", {
      href: "#",
      style: {
        color: 'var(--brand-accent)'
      }
    }, "WhatsApp")))), /*#__PURE__*/React.createElement(Card, {
      tone: "accent",
      gap: 10
    }, /*#__PURE__*/React.createElement("span", {
      style: lab
    }, "E-mail"), /*#__PURE__*/React.createElement("a", {
      href: "#",
      style: {
        font: '900 22px var(--font-display)',
        color: 'var(--text-body)'
      }
    }, EMAIL), /*#__PURE__*/React.createElement("span", {
      style: {
        fontSize: 15,
        fontWeight: 700
      }
    }, "Kdy volat: Po\u2013P\xE1 8\u201317"))), /*#__PURE__*/React.createElement("div", {
      style: {
        display: 'grid',
        gridTemplateColumns: 'repeat(auto-fit,minmax(min(100%,340px),1fr))',
        gap: 20
      }
    }, /*#__PURE__*/React.createElement(Card, {
      tone: "muted",
      style: {
        fontSize: 16,
        lineHeight: 1.5
      }
    }, /*#__PURE__*/React.createElement("h2", {
      style: {
        margin: 0,
        font: '900 20px var(--font-display)',
        textTransform: 'uppercase'
      }
    }, "Dal\u0161\xED informace"), /*#__PURE__*/React.createElement("span", null, /*#__PURE__*/React.createElement("strong", null, "N\xE1stupn\xED m\xEDsta:"), " ", /*#__PURE__*/React.createElement("a", {
      href: "#onas",
      style: {
        fontWeight: 700
      }
    }, "najdete na str\xE1nce O n\xE1s")), /*#__PURE__*/React.createElement("span", null, /*#__PURE__*/React.createElement("strong", null, "Hodnocen\xED:"), " Byli jste u n\xE1s spokojen\xED? Ohodno\u0165te n\xE1s na Googlu.")), /*#__PURE__*/React.createElement(Card, {
      tone: "muted",
      gap: 8,
      style: {
        fontSize: 15,
        lineHeight: 1.55
      }
    }, /*#__PURE__*/React.createElement("h2", {
      style: {
        margin: '0 0 4px',
        font: '900 20px var(--font-display)',
        textTransform: 'uppercase'
      }
    }, "\xDAdaje o provozovateli"), /*#__PURE__*/React.createElement("span", null, "[Jm\xE9no a p\u0159\xEDjmen\xED] \u2013 Auto\u0161kola U Bour\xE1ka"), /*#__PURE__*/React.createElement("span", null, "I\u010CO: [doplnit]"), /*#__PURE__*/React.createElement("span", null, "M\xEDsto podnik\xE1n\xED: [doplnit]"), /*#__PURE__*/React.createElement("span", {
      style: {
        color: 'var(--text-muted)'
      }
    }, "Fyzick\xE1 osoba zapsan\xE1 v \u017Eivnostensk\xE9m rejst\u0159\xEDku. Nejsem pl\xE1tce DPH.")))));
  }
  window.Contact = Contact;
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/website/Contact.jsx", error: String((e && e.message) || e) }); }

// ui_kits/website/CourseB.jsx
try { (() => {
(() => {
  const {
    PageHero,
    PlanCard,
    Card,
    CheckList,
    Notice,
    Button
  } = window.SimpleWebDesignSystem_2e6a6c;
  function CourseB({
    full
  }) {
    return /*#__PURE__*/React.createElement(Shell, {
      active: "kurzy"
    }, /*#__PURE__*/React.createElement(PageHero, {
      back: "Kurzy",
      backHref: "#kurzy",
      title: "\u0158idi\u010D\xE1k skupiny B",
      lead: "Dva kurzy se stejnou teori\xED i stejn\xFDm po\u010Dtem j\xEDzd. Li\u0161\xED se jen t\xEDm, jak \u010Dasto jezd\xEDte."
    }), /*#__PURE__*/React.createElement("main", {
      style: {
        maxWidth: 1000,
        margin: '0 auto',
        padding: '16px 24px 80px',
        display: 'flex',
        flexDirection: 'column',
        gap: 56
      }
    }, /*#__PURE__*/React.createElement("div", {
      style: {
        display: 'flex',
        flexDirection: 'column',
        gap: 20
      }
    }, full && /*#__PURE__*/React.createElement(Notice, null, /*#__PURE__*/React.createElement("strong", null, "Aktu\xE1ln\xED kurz je pln\xFD."), " P\u0159ihl\xE1sit se ale m\u016F\u017Eete i te\u010F \u2013 za\u0159ad\xEDme V\xE1s do dal\u0161\xEDho kurzu, kter\xFD za\u010D\xEDn\xE1 v listopadu."), /*#__PURE__*/React.createElement("div", {
      style: {
        display: 'grid',
        gridTemplateColumns: 'repeat(auto-fit,minmax(min(100%,360px),1fr))',
        gap: 28
      }
    }, /*#__PURE__*/React.createElement(PlanCard, {
      eyebrow: "Skupina B",
      title: "Z\xE1kladn\xED kurz",
      price: "22 000 K\u010D",
      priceNote: "nebo 2 \xD7 11 000 K\u010D",
      href: "#prihlaska",
      features: ['teorie 2× týdně odpoledne v malé skupině (max. 5 lidí)', '14 jízd po 90 minutách, 1–2× týdně', 'všechny zákonné hodiny výuky a výcviku', 'plánování jízd a cvičné testy v aplikaci Moje autoškola']
    }), /*#__PURE__*/React.createElement(PlanCard, {
      tone: "accent",
      eyebrow: "Skupina B",
      title: "Zrychlen\xFD kurz",
      price: "29 000 K\u010D",
      priceNote: "nebo 2 \xD7 14 500 K\u010D",
      href: "#prihlaska",
      features: ['teorie 2× týdně odpoledne v malé skupině (max. 5 lidí)', '14 jízd po 90 minutách, až 4× týdně', 'přednost při plánování jízd', 'plánování jízd a cvičné testy v aplikaci Moje autoškola']
    }))), /*#__PURE__*/React.createElement("div", {
      style: {
        display: 'grid',
        gridTemplateColumns: 'repeat(auto-fit,minmax(min(100%,320px),1fr))',
        gap: 20
      }
    }, /*#__PURE__*/React.createElement(Card, {
      tone: "muted",
      gap: 14
    }, /*#__PURE__*/React.createElement("h2", {
      style: {
        margin: 0,
        font: 'var(--type-card-title)',
        textTransform: 'uppercase'
      }
    }, "Co je v cen\u011B"), /*#__PURE__*/React.createElement(CheckList, {
      items: ['11 lekcí teorie na učebně podle individuálního studijního plánu', '14 jízd po 90 minutách s instruktorem', 'úvodní schůzka s oběma instruktory', 'přístup do aplikace Moje autoškola s plánováním a cvičnými testy']
    })), /*#__PURE__*/React.createElement(Card, {
      tone: "muted",
      gap: 14
    }, /*#__PURE__*/React.createElement("h2", {
      style: {
        margin: 0,
        font: 'var(--type-card-title)',
        textTransform: 'uppercase'
      }
    }, "Co v cen\u011B nen\xED"), /*#__PURE__*/React.createElement("div", {
      style: {
        display: 'flex',
        flexDirection: 'column',
        gap: 14,
        fontSize: 16,
        lineHeight: 1.45
      }
    }, /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("div", {
      style: {
        display: 'flex',
        justifyContent: 'space-between'
      }
    }, /*#__PURE__*/React.createElement("strong", null, "Poplatek za zkou\u0161ku"), /*#__PURE__*/React.createElement("strong", null, "600 K\u010D")), /*#__PURE__*/React.createElement("span", {
      style: {
        color: 'var(--text-muted)',
        fontSize: 15
      }
    }, "za auto a instruktora u z\xE1v\u011Bre\u010Dn\xE9 zkou\u0161ky")), /*#__PURE__*/React.createElement("div", {
      style: {
        display: 'flex',
        justifyContent: 'space-between'
      }
    }, /*#__PURE__*/React.createElement("strong", null, "Spr\xE1vn\xED poplatek \xFA\u0159adu"), /*#__PURE__*/React.createElement("strong", null, "700 K\u010D")), /*#__PURE__*/React.createElement("div", null, /*#__PURE__*/React.createElement("strong", null, "Posudek od l\xE9ka\u0159e"), /*#__PURE__*/React.createElement("br", null), /*#__PURE__*/React.createElement("span", {
      style: {
        color: 'var(--text-muted)',
        fontSize: 15
      }
    }, "cenu ur\u010Duje V\xE1\u0161 praktick\xFD l\xE9ka\u0159"))))), /*#__PURE__*/React.createElement(Card, {
      tone: "dark",
      padding: "clamp(24px,4vw,36px)"
    }, /*#__PURE__*/React.createElement("h2", {
      style: {
        margin: 0,
        font: 'var(--type-card-title)',
        textTransform: 'uppercase'
      }
    }, "Kdy\u017E nem\u016F\u017Eete p\u0159ij\xEDt"), /*#__PURE__*/React.createElement("p", {
      style: {
        margin: 0,
        fontSize: 16,
        lineHeight: 1.6,
        opacity: .9
      }
    }, "J\xEDzdu i lekci teorie m\u016F\u017Eete zdarma zru\u0161it ", /*#__PURE__*/React.createElement("strong", {
      style: {
        color: 'var(--brand-accent)'
      }
    }, "nejpozd\u011Bji 48 hodin p\u0159edem"), ". P\u0159i pozd\u011Bj\u0161\xEDm zru\u0161en\xED nebo nep\u0159\xEDchodu plat\xEDte ", /*#__PURE__*/React.createElement("strong", {
      style: {
        color: 'var(--brand-accent)'
      }
    }, "800 K\u010D"), " za j\xEDzdu a ", /*#__PURE__*/React.createElement("strong", {
      style: {
        color: 'var(--brand-accent)'
      }
    }, "400 K\u010D"), " za lekci teorie.")), /*#__PURE__*/React.createElement("div", {
      style: {
        display: 'grid',
        gridTemplateColumns: 'repeat(auto-fit,minmax(min(100%,300px),1fr))',
        gap: 20
      }
    }, [['Řidičák od 17', 'Chcete začít jezdit už v 17? Podívejte se, jak to funguje.', 'Zjistit víc'], ['Jak to probíhá', 'Celý postup od přihlášky po řidičák krok za krokem.', 'Zobrazit postup'], ['Časté dotazy', 'Kolik to stojí celkem, jak dlouho kurz trvá, co když neuděláte zkoušku.', 'Přečíst odpovědi']].map(([t, x, c]) => /*#__PURE__*/React.createElement(Card, {
      key: t,
      tone: "tint",
      href: "#",
      padding: 26,
      gap: 8
    }, /*#__PURE__*/React.createElement("strong", {
      style: {
        font: '900 20px var(--font-display)',
        color: 'var(--text-body)'
      }
    }, t), /*#__PURE__*/React.createElement("span", {
      style: {
        fontSize: 15,
        lineHeight: 1.5,
        color: 'var(--text-soft)'
      }
    }, x), /*#__PURE__*/React.createElement("span", {
      style: {
        fontWeight: 800,
        color: 'var(--brand-primary)'
      }
    }, c, " \u2192")))), /*#__PURE__*/React.createElement("div", {
      style: {
        alignSelf: 'center'
      }
    }, /*#__PURE__*/React.createElement(Button, {
      size: "xl",
      href: "#prihlaska"
    }, "P\u0159ihl\xE1sit se do kurzu"))));
  }
  window.CourseB = CourseB;
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/website/CourseB.jsx", error: String((e && e.message) || e) }); }

// ui_kits/website/Home.jsx
try { (() => {
function _extends() { return _extends = Object.assign ? Object.assign.bind() : function (n) { for (var e = 1; e < arguments.length; e++) { var t = arguments[e]; for (var r in t) ({}).hasOwnProperty.call(t, r) && (n[r] = t[r]); } return n; }, _extends.apply(null, arguments); }
(() => {
  const {
    Button,
    Pill,
    Card,
    CourseCard,
    ReasonItem,
    Steps,
    Accordion,
    Section,
    Eyebrow,
    ArrowLink,
    Avatar
  } = window.SimpleWebDesignSystem_2e6a6c;
  const COURSES = [['Řidičák skupiny B', 'Základní kurz', '22 000 Kč', 'Teorie 2× týdně, 14 jízd v klidném tempu 1–2× týdně.', '#kurz-b'], ['Řidičák skupiny B', 'Zrychlený kurz', '29 000 Kč', 'Teorie 2× týdně, až 4 jízdy týdně a přednost při plánování.', '#kurz-b'], ['Pro řidiče', 'Kondiční jízdy', 'od 1 300 Kč', 'Pro jistotu za volantem po pauze nebo po nehodě.', '#cenik'], ['Pro řidiče', 'Kurz parkování', '900 Kč', '45 minut jen o parkování.', '#cenik'], ['Pro řidiče', 'Dálniční kurz', '1 500 Kč', 'Dálnice D1 kousek od města.', '#cenik']];
  const REASONS = [['Malá autoškola, osobní přístup', 'Učí Vás dva instruktoři, kteří Vás budou znát jménem. Žádná fabrika na řidičáky.'], ['Teorie v malé skupině', 'Na lekci teorie je nejvýš 5 lidí. Na Vaše otázky tak vždycky zbude čas.'], ['Začít můžete kdykoli', 'Lekce teorie se opakují dokola, takže nemusíte čekat na začátek kurzu.'], ['Ceny bez překvapení', 'Všechno najdete v ceníku, včetně toho, co v ceně kurzu není.'], ['Jízdy si plánujete v aplikaci', 'Termíny jízd i lekcí teorie si zapisujete v aplikaci Moje autoškola. Najdete tam i cvičné testy.'], ['Parkování a zkouška nanečisto', 'Kurz zaměřený jen na parkování a zkoušku nanečisto, díky které půjdete k ostré zkoušce v klidu.']];
  const FAQS = [['Kolik mě řidičák bude stát celkem?', 'U Základního kurzu počítejte s částkami: kurz 22 000 Kč, auto a instruktor u zkoušky 600 Kč a správní poplatek úřadu 700 Kč. Dohromady tedy 23 300 Kč.'], ['Jak dlouho kurz trvá?', 'Záleží hlavně na tom, jak často jezdíte. V Základním kurzu jezdíte 1–2× týdně, ve Zrychleném až 4× týdně.'], ['Můžu nastoupit kdykoli?', 'Ano. Lekce teorie se opakují dokola, takže nemusíte čekat na začátek kurzu.'], ['Můžu platit na dvakrát?', 'Ano. U Řidičského kurzu můžete zaplatit nejdřív polovinu a druhou polovinu před 7. jízdou.']];
  function TempoPicker() {
    const [t, setT] = React.useState(null);
    const b = (on, c) => ({
      cursor: 'pointer',
      border: '2px solid ' + c,
      padding: '12px 22px',
      borderRadius: 999,
      fontWeight: 800,
      fontSize: 15,
      background: on ? c : '#fff',
      color: on && c.includes('primary') ? '#fff' : 'var(--text-body)',
      fontFamily: 'inherit'
    });
    return /*#__PURE__*/React.createElement("section", {
      style: {
        maxWidth: 1200,
        margin: '-24px auto 0',
        padding: '0 24px',
        position: 'relative'
      }
    }, /*#__PURE__*/React.createElement("div", {
      style: {
        background: '#fff',
        borderRadius: 20,
        boxShadow: 'var(--shadow-float)',
        padding: '28px 32px',
        display: 'flex',
        gap: 24,
        alignItems: 'center',
        flexWrap: 'wrap'
      }
    }, /*#__PURE__*/React.createElement(Avatar, {
      src: LOGO,
      size: 84
    }), /*#__PURE__*/React.createElement("div", {
      style: {
        flex: '1 1 300px',
        display: 'flex',
        flexDirection: 'column',
        gap: 4
      }
    }, /*#__PURE__*/React.createElement("strong", {
      style: {
        font: '800 20px var(--font-display)'
      }
    }, "Dobr\xFD den, jsem \u017Eelva Bour\xE1k!"), /*#__PURE__*/React.createElement("span", {
      style: {
        fontSize: 16,
        color: 'var(--text-muted)',
        lineHeight: 1.5
      }
    }, "Pom\u016F\u017Eu V\xE1m vybrat kurz. Jak \u010Dasto chcete jezdit?")), /*#__PURE__*/React.createElement("div", {
      style: {
        display: 'flex',
        gap: 10,
        flexWrap: 'wrap'
      }
    }, /*#__PURE__*/React.createElement("button", {
      onClick: () => setT('slow'),
      style: b(t === 'slow', 'var(--brand-primary)')
    }, "V klidu, 1\u20132\xD7 t\xFDdn\u011B"), /*#__PURE__*/React.createElement("button", {
      onClick: () => setT('fast'),
      style: b(t === 'fast', 'var(--brand-accent)')
    }, "Co nejd\u0159\xEDv, a\u017E 4\xD7 t\xFDdn\u011B")), t && /*#__PURE__*/React.createElement("a", {
      href: "#kurz-b",
      style: {
        flexBasis: '100%',
        fontWeight: 700,
        fontSize: 15
      }
    }, t === 'fast' ? 'Doporučujeme Zrychlený kurz za 29 000 Kč' : 'Doporučujeme Základní kurz za 22 000 Kč', " \u2192")));
  }
  function Home() {
    const groups = [];
    COURSES.forEach(([g, name, price, desc, href]) => {
      let x = groups.find(y => y.title === g);
      if (!x) groups.push(x = {
        title: g,
        items: []
      });
      x.items.push({
        name,
        price,
        desc,
        href
      });
    });
    const grid = m => ({
      display: 'grid',
      gridTemplateColumns: `repeat(auto-fit,minmax(min(100%,${m}px),1fr))`,
      gap: 20
    });
    return /*#__PURE__*/React.createElement(Shell, {
      active: ""
    }, /*#__PURE__*/React.createElement("section", {
      style: {
        background: 'var(--gradient-hero)'
      }
    }, /*#__PURE__*/React.createElement("div", {
      style: {
        maxWidth: 1200,
        margin: '0 auto',
        padding: '56px 24px 64px',
        display: 'grid',
        gridTemplateColumns: 'repeat(auto-fit,minmax(min(100%,440px),1fr))',
        gap: 40,
        alignItems: 'center'
      }
    }, /*#__PURE__*/React.createElement("div", {
      style: {
        display: 'flex',
        flexDirection: 'column',
        gap: 22
      }
    }, /*#__PURE__*/React.createElement("h1", {
      style: {
        margin: 0,
        font: 'var(--type-h1)',
        textTransform: 'uppercase'
      }
    }, "Auto\u0161kola ", /*#__PURE__*/React.createElement("span", {
      style: {
        color: 'var(--brand-primary)'
      }
    }, "U Bour\xE1ka"), " ve Velk\xE9m Mezi\u0159\xED\u010D\xED"), /*#__PURE__*/React.createElement("p", {
      style: {
        margin: 0,
        font: 'var(--type-lead-display)'
      }
    }, "A\u0165 jste rychl\xED, nebo pomal\xED \u2013 my V\xE1s \u0159\xEDdit nau\u010D\xEDme."), /*#__PURE__*/React.createElement("p", {
      style: {
        margin: 0,
        fontSize: 17,
        lineHeight: 1.6,
        maxWidth: 520,
        color: 'var(--text-muted)'
      }
    }, "Jsme mal\xE1 auto\u0161kola se dv\u011Bma instruktory. Teorii se u\u010D\xEDte v mal\xE9 skupin\u011B, j\xEDzdy si pl\xE1nujete v aplikaci a za\u010D\xEDt m\u016F\u017Eete kdykoli."), /*#__PURE__*/React.createElement("div", {
      style: {
        display: 'flex',
        gap: 12,
        flexWrap: 'wrap'
      }
    }, /*#__PURE__*/React.createElement(Button, {
      glow: true,
      href: "#prihlaska"
    }, "P\u0159ihl\xE1sit se do kurzu"), /*#__PURE__*/React.createElement(Button, {
      variant: "secondary",
      href: "#cenik"
    }, "Zobrazit cen\xEDk")), /*#__PURE__*/React.createElement("div", {
      style: {
        display: 'flex',
        gap: 8,
        flexWrap: 'wrap'
      }
    }, ['Řidičák skupiny B', 'Kondiční jízdy', 'Kurz parkování', 'Velké Meziříčí a okolí'].map(p => /*#__PURE__*/React.createElement(Pill, {
      key: p
    }, p)))), /*#__PURE__*/React.createElement("div", {
      style: {
        display: 'grid',
        placeItems: 'center'
      }
    }, /*#__PURE__*/React.createElement("img", {
      src: LOGO,
      alt: "",
      style: {
        width: '100%',
        maxWidth: 500,
        mixBlendMode: 'multiply'
      }
    })))), /*#__PURE__*/React.createElement(TempoPicker, null), /*#__PURE__*/React.createElement(Section, {
      title: "Pro\u010D se u\u010Dit \u0159\xEDdit u n\xE1s",
      padBottom: 0
    }, /*#__PURE__*/React.createElement("div", {
      style: grid(320)
    }, REASONS.map(([t, x], i) => /*#__PURE__*/React.createElement(ReasonItem, {
      key: i,
      n: i + 1,
      title: t,
      text: x
    })))), /*#__PURE__*/React.createElement(Section, {
      title: "Co u n\xE1s m\u016F\u017Eete absolvovat"
    }, groups.map(g => /*#__PURE__*/React.createElement("div", {
      key: g.title,
      style: {
        display: 'flex',
        flexDirection: 'column',
        gap: 16
      }
    }, /*#__PURE__*/React.createElement(Eyebrow, null, g.title), /*#__PURE__*/React.createElement("div", {
      style: grid(300)
    }, g.items.map(c => /*#__PURE__*/React.createElement(CourseCard, _extends({
      key: c.name
    }, c))))))), /*#__PURE__*/React.createElement(Section, {
      tone: "muted",
      title: "Jak se dostanete k \u0159idi\u010D\xE1ku"
    }, /*#__PURE__*/React.createElement(Steps, {
      items: [{
        title: 'Přihláška a platba',
        text: 'Vyberete kurz, vyplníte přihlášku a zaplatíte převodem, celé, nebo polovinu.'
      }, {
        title: 'Úvodní schůzka',
        text: 'Půl hodiny s oběma instruktory. Ukážeme Vám, jak kurz probíhá, a vše potřebné podepíšeme.'
      }, {
        title: 'Teorie a jízdy',
        text: 'Lekce teorie a 14 jízd si plánujete v aplikaci podle sebe.'
      }, {
        title: 'Zkouška',
        text: 'Po dokončení kurzu Vás přihlásíme ke zkoušce na Městském úřadě Velké Meziříčí.'
      }]
    }), /*#__PURE__*/React.createElement("div", {
      style: {
        alignSelf: 'center'
      }
    }, /*#__PURE__*/React.createElement(ArrowLink, {
      href: "#postup"
    }, "Jak to prob\xEDh\xE1 podrobn\u011B"))), /*#__PURE__*/React.createElement("section", {
      style: {
        maxWidth: 1200,
        margin: '0 auto',
        padding: '80px 24px'
      }
    }, /*#__PURE__*/React.createElement(Card, {
      tone: "dark",
      radius: "xl",
      padding: "clamp(28px,5vw,48px)",
      style: {
        display: 'grid',
        gridTemplateColumns: 'repeat(auto-fit,minmax(min(100%,320px),1fr))',
        gap: 32,
        alignItems: 'center'
      }
    }, /*#__PURE__*/React.createElement("div", {
      style: {
        display: 'flex',
        flexDirection: 'column',
        gap: 14
      }
    }, /*#__PURE__*/React.createElement("h2", {
      style: {
        margin: 0,
        font: '900 clamp(28px,3.6vw,40px)/1.15 var(--font-display)',
        textTransform: 'uppercase'
      }
    }, "Kdo V\xE1s nau\u010D\xED ", /*#__PURE__*/React.createElement("span", {
      style: {
        color: 'var(--brand-accent)'
      }
    }, "\u0159\xEDdit")), /*#__PURE__*/React.createElement("p", {
      style: {
        margin: 0,
        fontSize: 17,
        lineHeight: 1.6,
        opacity: .85
      }
    }, "Dominik a Ond\u0159ej. Dva instrukto\u0159i, jedno auto a dost trp\u011Blivosti pro ka\u017Ed\xE9ho."), /*#__PURE__*/React.createElement(ArrowLink, {
      tone: "accent",
      href: "#onas"
    }, "V\xEDce o n\xE1s")), /*#__PURE__*/React.createElement("div", {
      style: {
        display: 'flex',
        gap: 20,
        justifyContent: 'center'
      }
    }, [['D', 'primary', 'Dominik'], ['O', 'accent', 'Ondřej']].map(([i, t, n]) => /*#__PURE__*/React.createElement("div", {
      key: n,
      style: {
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        gap: 10
      }
    }, /*#__PURE__*/React.createElement(Avatar, {
      initial: i,
      tone: t
    }), /*#__PURE__*/React.createElement("strong", null, n)))))), /*#__PURE__*/React.createElement(Section, {
      tone: "muted",
      width: "860px",
      gap: 32,
      title: "Na co se n\xE1s \u010Dasto pt\xE1te"
    }, /*#__PURE__*/React.createElement(Accordion, {
      items: FAQS.map(([q, a]) => ({
        q,
        a
      }))
    }), /*#__PURE__*/React.createElement("div", {
      style: {
        alignSelf: 'center'
      }
    }, /*#__PURE__*/React.createElement(ArrowLink, {
      href: "#dotazy"
    }, "V\u0161echny \u010Dast\xE9 dotazy"))), /*#__PURE__*/React.createElement("section", {
      style: {
        maxWidth: 1200,
        margin: '0 auto',
        padding: '80px 24px',
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        textAlign: 'center',
        gap: 18
      }
    }, /*#__PURE__*/React.createElement("h2", {
      style: {
        margin: 0,
        font: 'var(--type-h2)',
        textTransform: 'uppercase'
      }
    }, "M\xE1te ot\xE1zku? Zavolejte n\xE1m."), /*#__PURE__*/React.createElement("p", {
      style: {
        margin: 0,
        fontSize: 17,
        lineHeight: 1.6,
        color: 'var(--text-muted)',
        maxWidth: 560
      }
    }, "Volejte Po\u2013P\xE1 8\u201317. Jindy n\xE1m napi\u0161te SMS nebo zpr\xE1vu na WhatsApp a ozveme se."), /*#__PURE__*/React.createElement("div", {
      style: {
        display: 'flex',
        gap: '12px 28px',
        flexWrap: 'wrap',
        justifyContent: 'center',
        fontSize: 17,
        fontWeight: 700
      }
    }, /*#__PURE__*/React.createElement("span", null, "Dominik ", /*#__PURE__*/React.createElement("a", {
      href: "#"
    }, PHONE)), /*#__PURE__*/React.createElement("span", null, "Ond\u0159ej ", /*#__PURE__*/React.createElement("a", {
      href: "#"
    }, PHONE)), /*#__PURE__*/React.createElement("a", {
      href: "#"
    }, EMAIL)), /*#__PURE__*/React.createElement("div", {
      style: {
        marginTop: 8
      }
    }, /*#__PURE__*/React.createElement(Button, {
      href: "#prihlaska"
    }, "P\u0159ihl\xE1sit se do kurzu"))));
  }
  window.Home = Home;
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/website/Home.jsx", error: String((e && e.message) || e) }); }

// ui_kits/website/Pricing.jsx
try { (() => {
(() => {
  const {
    PageHero,
    PriceTable,
    Button
  } = window.SimpleWebDesignSystem_2e6a6c;
  function Pricing() {
    const L = (t, h = '#kurz-b') => /*#__PURE__*/React.createElement("a", {
        href: h,
        style: {
          fontWeight: 700
        }
      }, t),
      M = t => /*#__PURE__*/React.createElement("span", {
        style: {
          color: 'var(--text-muted)'
        }
      }, t);
    return /*#__PURE__*/React.createElement(Shell, {
      active: "cenik"
    }, /*#__PURE__*/React.createElement(PageHero, {
      title: "Cen\xEDk",
      lead: "V\u0161echny ceny na jednom m\xEDst\u011B, v\u010Detn\u011B toho, co v cen\u011B kurzu nen\xED."
    }), /*#__PURE__*/React.createElement("main", {
      style: {
        maxWidth: 960,
        margin: '0 auto',
        padding: '24px 24px 80px',
        display: 'flex',
        flexDirection: 'column',
        gap: 28
      }
    }, /*#__PURE__*/React.createElement(PriceTable, {
      title: "\u0158idi\u010D\xE1k skupiny B",
      columns: ['Kurz', 'Najednou', 'Ve dvou polovinách'],
      rows: [[L('Základní kurz'), '22 000 Kč', '2 × 11 000 Kč'], [L('Zrychlený kurz'), '29 000 Kč', '2 × 14 500 Kč']]
    }), /*#__PURE__*/React.createElement(PriceTable, {
      title: "J\xEDzdy nav\xEDc",
      rows: [['1 jízda', '900 Kč', '900 Kč / jízda'], ['3 jízdy', '2 500 Kč', '833 Kč / jízda'], ['6 jízd', '4 800 Kč', '800 Kč / jízda']]
    }), /*#__PURE__*/React.createElement(PriceTable, {
      title: "Pro \u0159idi\u010De",
      rows: [[/*#__PURE__*/React.createElement("span", null, L('Kondiční jízdy', '#'), " \u2013 1 j\xEDzda (90 min)"), '1 500 Kč'], [/*#__PURE__*/React.createElement("span", null, L('Kondiční jízdy', '#'), " \u2013 3 j\xEDzdy ", M('(1 400 Kč / jízda)')), '4 200 Kč'], [/*#__PURE__*/React.createElement("span", null, L('Kurz parkování', '#'), " \u2013 45 min"), '900 Kč'], [/*#__PURE__*/React.createElement("span", null, L('Dálniční kurz', '#'), " \u2013 90 min"), '1 500 Kč']]
    }), /*#__PURE__*/React.createElement(PriceTable, {
      title: "Zkou\u0161ka a spr\xE1vn\xED poplatky",
      columns: ['Položka', 'Autoškole', 'Úřadu'],
      rows: [['Závěrečná zkouška', '600 Kč', /*#__PURE__*/React.createElement("strong", null, "700 K\u010D")], ['Opravný test', '300 Kč', /*#__PURE__*/React.createElement("strong", null, "100 K\u010D")], ['Opravná jízda', '600 Kč', /*#__PURE__*/React.createElement("strong", null, "400 K\u010D")]]
    }), /*#__PURE__*/React.createElement(PriceTable, {
      title: "Storno",
      intro: "Zdarma p\u0159i zru\u0161en\xED nejpozd\u011Bji 48 hodin p\u0159edem. S potvrzen\xEDm od l\xE9ka\u0159e nic neplat\xEDte.",
      rows: [['Pozdní zrušení nebo nepříchod – jízda', '800 Kč'], ['Pozdní zrušení nebo nepříchod – lekce teorie', '400 Kč']]
    }), /*#__PURE__*/React.createElement("p", {
      style: {
        margin: 0,
        fontSize: 15,
        lineHeight: 1.6,
        color: 'var(--text-muted)',
        textAlign: 'center'
      }
    }, "Ceny jsou kone\u010Dn\xE9, nejsem pl\xE1tce DPH. Plat\xED se p\u0159evodem na \xFA\u010Det. Podrobnosti najdete v ", /*#__PURE__*/React.createElement("a", {
      href: "#",
      style: {
        fontWeight: 700
      }
    }, "obchodn\xEDch podm\xEDnk\xE1ch"), "."), /*#__PURE__*/React.createElement("div", {
      style: {
        alignSelf: 'center'
      }
    }, /*#__PURE__*/React.createElement(Button, {
      href: "#prihlaska"
    }, "P\u0159ihl\xE1sit se do kurzu"))));
  }
  window.Pricing = Pricing;
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/website/Pricing.jsx", error: String((e && e.message) || e) }); }

// ui_kits/website/Shell.jsx
try { (() => {
(() => {
  const {
    TopBar,
    SiteHeader,
    SiteFooter,
    MobileActionBar
  } = window.SimpleWebDesignSystem_2e6a6c;
  const LOGO = '../../assets/logo-sample.jpg';
  const NAV = [['kurzy', 'Kurzy'], ['cenik', 'Ceník'], ['postup', 'Jak to probíhá'], ['onas', 'O nás'], ['dotazy', 'Časté dotazy'], ['kontakt', 'Kontakt']].map(([id, label]) => ({
    id,
    label,
    href: '#' + id
  }));
  const PHONE = '+420 000 000 000',
    EMAIL = 'info@ubouraka.cz';
  function useMobile(bp) {
    const [m, setM] = React.useState(false);
    React.useEffect(() => {
      const r = () => setM(window.innerWidth < bp);
      r();
      window.addEventListener('resize', r);
      return () => window.removeEventListener('resize', r);
    }, [bp]);
    return m;
  }
  function Shell({
    active,
    children
  }) {
    const mobile = useMobile(720);
    return /*#__PURE__*/React.createElement("div", {
      style: {
        minHeight: '100vh',
        overflowX: 'hidden'
      }
    }, /*#__PURE__*/React.createElement(TopBar, {
      phone: PHONE,
      email: EMAIL,
      hours: "Volejte Po\u2013P\xE1 8\u201317",
      linkLabel: "Pro \u017E\xE1ky"
    }), /*#__PURE__*/React.createElement(SiteHeader, {
      logoSrc: LOGO,
      homeHref: "#uvod",
      items: NAV,
      active: active,
      ctaHref: "#prihlaska",
      extraMobileLinks: [{
        label: 'Pro žáky',
        href: '#'
      }]
    }), children, /*#__PURE__*/React.createElement(SiteFooter, {
      tagline: "A\u0165 jste rychl\xED, nebo pomal\xED \u2013 my V\xE1s \u0159\xEDdit nau\u010D\xEDme.",
      socials: ['Facebook', 'Instagram', 'TikTok'].map(l => ({
        label: l,
        icon: '../../assets/icons/' + l.toLowerCase() + '.svg'
      })),
      columns: [{
        title: 'Kontakt',
        links: [{
          label: 'Dominik: ' + PHONE
        }, {
          label: 'Ondřej Dvořák: ' + PHONE
        }, {
          label: EMAIL,
          href: 'mailto:' + EMAIL
        }, {
          label: 'Volejte Po–Pá 8–17, jindy napište SMS nebo zprávu na WhatsApp.',
          muted: true
        }]
      }, {
        title: 'Odkazy',
        links: [['Kurzy', '#kurzy'], ['Ceník', '#cenik'], ['Jak to probíhá', '#postup'], ['O nás', '#onas'], ['Časté dotazy', '#dotazy'], ['Kontakt', '#kontakt']].map(([label, href]) => ({
          label,
          href
        }))
      }],
      legalTitle: "Povinn\xE9 \xFAdaje",
      legal: ['[Jméno a příjmení], IČO [doplnit], místo podnikání [doplnit]. Fyzická osoba zapsaná v živnostenském rejstříku. Nejsem plátce DPH.', 'Mimosoudní řešení spotřebitelských sporů: Česká obchodní inspekce, www.coi.cz.'],
      copyright: "\xA9 2026 Auto\u0161kola U Bour\xE1ka",
      bottomLinks: [{
        label: 'Obchodní podmínky',
        href: '#'
      }, {
        label: 'Ochrana osobních údajů',
        href: '#'
      }, {
        label: 'Odstoupení od smlouvy',
        href: '#'
      }]
    }), mobile && /*#__PURE__*/React.createElement(React.Fragment, null, /*#__PURE__*/React.createElement("div", {
      style: {
        height: 72,
        background: 'var(--surface-footer)'
      }
    }), /*#__PURE__*/React.createElement(MobileActionBar, {
      callHref: "tel:+420000000000",
      ctaHref: "#prihlaska"
    })));
  }
  Object.assign(window, {
    Shell,
    LOGO,
    PHONE,
    EMAIL
  });
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/website/Shell.jsx", error: String((e && e.message) || e) }); }

// ui_kits/website/Signup.jsx
try { (() => {
(() => {
  const {
    Field,
    Input,
    Select,
    Textarea,
    ChoiceChips,
    Checkbox,
    Button,
    Avatar
  } = window.SimpleWebDesignSystem_2e6a6c;
  const OPTS = [['zakladni', 'Řidičák B – Základní kurz (22 000 Kč)'], ['zrychleny', 'Řidičák B – Zrychlený kurz (29 000 Kč)'], ['kondice1', 'Kondiční jízdy – 1 jízda (1 500 Kč)'], ['parkovani', 'Kurz parkování (900 Kč)'], ['dalnice', 'Dálniční kurz (1 500 Kč)'], ['nanecisto', 'Zkouška nanečisto (1 000 Kč)']];
  function Signup() {
    const blank = {
      course: 'zakladni',
      name: '',
      birth: '',
      email: '',
      phone: '',
      address: '',
      pay: 'Najednou',
      source: '—',
      note: '',
      terms: false
    };
    const [f, setF] = React.useState(blank),
      [E, setE] = React.useState({}),
      [sent, setSent] = React.useState(false);
    const set = k => e => {
      const v = e && e.target ? e.target.type === 'checkbox' ? e.target.checked : e.target.value : e;
      setF(s => ({
        ...s,
        [k]: v
      }));
      setE(s => ({
        ...s,
        [k]: ''
      }));
    };
    const rid = f.course === 'zakladni' || f.course === 'zrychleny';
    const submit = e => {
      e.preventDefault();
      const x = {},
        req = 'Vyplňte prosím toto pole.';
      ['name', 'birth', 'address'].forEach(k => {
        if (!f[k].trim()) x[k] = req;
      });
      if (!f.email.trim()) x.email = req;else if (!/^\S+@\S+\.\S+$/.test(f.email)) x.email = 'Zkontrolujte prosím e-mail, něco v něm chybí.';
      if (!f.phone.trim()) x.phone = req;else if (f.phone.replace(/\D/g, '').length < 9) x.phone = 'Zadejte prosím telefon včetně předvolby, nebo devět číslic.';
      if (!f.terms) x.terms = 'Pro odeslání je potřeba souhlasit s obchodními podmínkami.';
      if (Object.keys(x).length) return setE(x);
      setSent(true);
      window.scrollTo({
        top: 0,
        behavior: 'smooth'
      });
    };
    return /*#__PURE__*/React.createElement(Shell, {
      active: ""
    }, /*#__PURE__*/React.createElement("section", {
      style: {
        background: 'var(--surface-dark)',
        color: '#fff'
      }
    }, /*#__PURE__*/React.createElement("div", {
      style: {
        maxWidth: 1200,
        margin: '0 auto',
        padding: '56px 24px 80px',
        display: 'grid',
        gridTemplateColumns: 'repeat(auto-fit,minmax(min(100%,380px),1fr))',
        gap: 48,
        alignItems: 'start'
      }
    }, /*#__PURE__*/React.createElement("div", {
      style: {
        display: 'flex',
        flexDirection: 'column',
        gap: 22
      }
    }, /*#__PURE__*/React.createElement("h1", {
      style: {
        margin: 0,
        font: '900 clamp(36px,5vw,58px)/1.12 var(--font-display)',
        textTransform: 'uppercase'
      }
    }, "P\u0159ihl\xE1\u0161ka"), /*#__PURE__*/React.createElement("div", {
      style: {
        display: 'flex',
        flexDirection: 'column',
        gap: 10
      }
    }, /*#__PURE__*/React.createElement("strong", {
      style: {
        font: '800 18px var(--font-display)',
        color: 'var(--brand-accent)'
      }
    }, "Co bude n\xE1sledovat"), /*#__PURE__*/React.createElement("span", {
      style: {
        fontSize: 15,
        fontWeight: 800,
        opacity: .8
      }
    }, "\u0158idi\u010Dsk\xFD kurz"), /*#__PURE__*/React.createElement("ol", {
      style: {
        margin: 0,
        paddingLeft: 20,
        display: 'flex',
        flexDirection: 'column',
        gap: 6,
        fontSize: 16,
        lineHeight: 1.5,
        opacity: .9
      }
    }, /*#__PURE__*/React.createElement("li", null, "Po\u0161leme V\xE1m e-mail s potvrzen\xEDm a platebn\xEDmi \xFAdaji."), /*#__PURE__*/React.createElement("li", null, "Zaplat\xEDte p\u0159evodem."), /*#__PURE__*/React.createElement("li", null, "Zavol\xE1me V\xE1m a domluv\xEDme \xFAvodn\xED sch\u016Fzku.")), /*#__PURE__*/React.createElement("span", {
      style: {
        fontSize: 15,
        lineHeight: 1.5,
        opacity: .8,
        marginTop: 4
      }
    }, /*#__PURE__*/React.createElement("strong", null, "Ostatn\xED kurzy:"), " m\xEDsto \xFAvodn\xED sch\u016Fzky domluv\xEDme term\xEDn prvn\xED j\xEDzdy.")), /*#__PURE__*/React.createElement("div", {
      style: {
        borderTop: '1px solid var(--border-on-dark)',
        paddingTop: 22,
        display: 'flex',
        flexDirection: 'column',
        gap: 8,
        fontSize: 16
      }
    }, /*#__PURE__*/React.createElement("strong", {
      style: {
        font: '800 18px var(--font-display)'
      }
    }, "M\xE1te ot\xE1zku? Zavolejte n\xE1m."), /*#__PURE__*/React.createElement("span", null, "Dominik: ", /*#__PURE__*/React.createElement("a", {
      href: "#",
      style: {
        color: 'var(--brand-accent)',
        fontWeight: 800
      }
    }, PHONE)), /*#__PURE__*/React.createElement("span", null, "Ond\u0159ej: ", /*#__PURE__*/React.createElement("a", {
      href: "#",
      style: {
        color: 'var(--brand-accent)',
        fontWeight: 800
      }
    }, PHONE)), /*#__PURE__*/React.createElement("a", {
      href: "#",
      style: {
        color: 'var(--brand-accent)',
        fontWeight: 800
      }
    }, EMAIL))), /*#__PURE__*/React.createElement("div", {
      style: {
        background: '#fff',
        color: 'var(--text-body)',
        borderRadius: 20,
        padding: 'clamp(22px,4vw,36px)',
        boxShadow: 'var(--shadow-modal)'
      }
    }, !sent ? /*#__PURE__*/React.createElement("form", {
      onSubmit: submit,
      noValidate: true,
      style: {
        display: 'flex',
        flexDirection: 'column',
        gap: 16
      }
    }, /*#__PURE__*/React.createElement(Field, {
      label: "Kurz"
    }, /*#__PURE__*/React.createElement(Select, {
      value: f.course,
      onChange: set('course'),
      options: OPTS.map(([value, label]) => ({
        value,
        label
      }))
    })), /*#__PURE__*/React.createElement("div", {
      style: {
        display: 'grid',
        gridTemplateColumns: 'repeat(auto-fit,minmax(min(100%,200px),1fr))',
        gap: 14
      }
    }, /*#__PURE__*/React.createElement(Field, {
      label: "Jm\xE9no a p\u0159\xEDjmen\xED",
      error: E.name
    }, /*#__PURE__*/React.createElement(Input, {
      value: f.name,
      onChange: set('name'),
      error: !!E.name
    })), /*#__PURE__*/React.createElement(Field, {
      label: "Datum narozen\xED",
      error: E.birth
    }, /*#__PURE__*/React.createElement(Input, {
      type: "date",
      value: f.birth,
      onChange: set('birth'),
      error: !!E.birth,
      style: {
        padding: '12px 14px'
      }
    })), /*#__PURE__*/React.createElement(Field, {
      label: "E-mail",
      error: E.email
    }, /*#__PURE__*/React.createElement(Input, {
      type: "email",
      value: f.email,
      onChange: set('email'),
      error: !!E.email
    })), /*#__PURE__*/React.createElement(Field, {
      label: "Telefon",
      error: E.phone
    }, /*#__PURE__*/React.createElement(Input, {
      type: "tel",
      value: f.phone,
      onChange: set('phone'),
      error: !!E.phone
    }))), /*#__PURE__*/React.createElement(Field, {
      label: "Adresa trval\xE9ho bydli\u0161t\u011B",
      error: E.address
    }, /*#__PURE__*/React.createElement(Input, {
      value: f.address,
      onChange: set('address'),
      error: !!E.address
    })), rid && /*#__PURE__*/React.createElement("div", {
      style: {
        display: 'flex',
        flexDirection: 'column',
        gap: 8
      }
    }, /*#__PURE__*/React.createElement("span", {
      style: {
        fontWeight: 800,
        fontSize: 15
      }
    }, "Jak chcete platit?"), /*#__PURE__*/React.createElement(ChoiceChips, {
      options: ['Najednou', 'Ve dvou polovinách'],
      value: f.pay,
      onChange: set('pay')
    })), /*#__PURE__*/React.createElement(Field, {
      label: "Jak jste se o n\xE1s dozv\u011Bd\u011Bli?",
      optional: true
    }, /*#__PURE__*/React.createElement(Select, {
      value: f.source,
      onChange: set('source'),
      style: {
        fontWeight: 400
      },
      options: ['—', 'Google', 'Seznam', 'Instagram', 'Facebook', 'TikTok', 'Doporučení známých', 'Jinak']
    })), /*#__PURE__*/React.createElement(Field, {
      label: "Pozn\xE1mka",
      optional: true
    }, /*#__PURE__*/React.createElement(Textarea, {
      rows: 3,
      value: f.note,
      onChange: set('note')
    })), /*#__PURE__*/React.createElement(Checkbox, {
      checked: f.terms,
      onChange: set('terms')
    }, "Souhlas\xEDm s ", /*#__PURE__*/React.createElement("a", {
      href: "#"
    }, "obchodn\xEDmi podm\xEDnkami"), " a beru na v\u011Bdom\xED ", /*#__PURE__*/React.createElement("a", {
      href: "#"
    }, "zpracov\xE1n\xED osobn\xEDch \xFAdaj\u016F"), "."), E.terms && /*#__PURE__*/React.createElement("span", {
      style: {
        color: 'var(--danger)',
        fontSize: 13,
        fontWeight: 700,
        marginTop: -8
      }
    }, E.terms), /*#__PURE__*/React.createElement(Button, {
      type: "submit",
      size: "xl",
      block: true
    }, "Objedn\xE1vka zavazuj\xEDc\xED k platb\u011B")) : /*#__PURE__*/React.createElement("div", {
      style: {
        display: 'flex',
        flexDirection: 'column',
        gap: 16,
        alignItems: 'center',
        textAlign: 'center',
        padding: '16px 0'
      }
    }, /*#__PURE__*/React.createElement(Avatar, {
      src: LOGO,
      size: 110
    }), /*#__PURE__*/React.createElement("h2", {
      style: {
        margin: 0,
        font: '900 26px/1.25 var(--font-display)'
      }
    }, "D\u011Bkujeme, p\u0159ihl\xE1\u0161ka je odeslan\xE1"), /*#__PURE__*/React.createElement("p", {
      style: {
        margin: 0,
        fontSize: 16,
        lineHeight: 1.6,
        color: 'var(--text-muted)',
        maxWidth: 400
      }
    }, "Na e-mail ", /*#__PURE__*/React.createElement("strong", null, f.email), " jsme V\xE1m poslali potvrzen\xED, obchodn\xED podm\xEDnky a platebn\xED \xFAdaje s QR k\xF3dem."), /*#__PURE__*/React.createElement("div", {
      style: {
        display: 'flex',
        flexDirection: 'column',
        gap: 6,
        textAlign: 'left',
        maxWidth: 400,
        width: '100%'
      }
    }, /*#__PURE__*/React.createElement("strong", {
      style: {
        fontSize: 15
      }
    }, "Co d\xE1l:"), /*#__PURE__*/React.createElement("ol", {
      style: {
        margin: 0,
        paddingLeft: 20,
        display: 'flex',
        flexDirection: 'column',
        gap: 6,
        fontSize: 15,
        lineHeight: 1.5
      }
    }, /*#__PURE__*/React.createElement("li", null, "Zapla\u0165te p\u0159evodem do 7 dn\u016F."), /*#__PURE__*/React.createElement("li", null, "Jakmile platba doraz\xED, zavol\xE1me V\xE1m."))), /*#__PURE__*/React.createElement(Button, {
      variant: "outline",
      size: "sm",
      onClick: () => {
        setSent(false);
        setF(blank);
        setE({});
      }
    }, "Poslat dal\u0161\xED p\u0159ihl\xE1\u0161ku"))))));
  }
  window.Signup = Signup;
})();
})(); } catch (e) { __ds_ns.__errors.push({ path: "ui_kits/website/Signup.jsx", error: String((e && e.message) || e) }); }

__ds_ns.Card = __ds_scope.Card;

__ds_ns.CourseCard = __ds_scope.CourseCard;

__ds_ns.PlanCard = __ds_scope.PlanCard;

__ds_ns.Accordion = __ds_scope.Accordion;

__ds_ns.CheckList = __ds_scope.CheckList;

__ds_ns.PriceTable = __ds_scope.PriceTable;

__ds_ns.ReasonItem = __ds_scope.ReasonItem;

__ds_ns.Steps = __ds_scope.Steps;

__ds_ns.ArrowLink = __ds_scope.ArrowLink;

__ds_ns.Avatar = __ds_scope.Avatar;

__ds_ns.Button = __ds_scope.Button;

__ds_ns.Notice = __ds_scope.Notice;

__ds_ns.NumberBadge = __ds_scope.NumberBadge;

__ds_ns.Pill = __ds_scope.Pill;

__ds_ns.Checkbox = __ds_scope.Checkbox;

__ds_ns.ChoiceChips = __ds_scope.ChoiceChips;

__ds_ns.Field = __ds_scope.Field;

__ds_ns.Input = __ds_scope.Input;

__ds_ns.Select = __ds_scope.Select;

__ds_ns.Textarea = __ds_scope.Textarea;

__ds_ns.Eyebrow = __ds_scope.Eyebrow;

__ds_ns.MobileActionBar = __ds_scope.MobileActionBar;

__ds_ns.PageHero = __ds_scope.PageHero;

__ds_ns.Section = __ds_scope.Section;

__ds_ns.SiteFooter = __ds_scope.SiteFooter;

__ds_ns.SiteHeader = __ds_scope.SiteHeader;

__ds_ns.TopBar = __ds_scope.TopBar;

})();
