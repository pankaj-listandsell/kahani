{{-- Viral studios (Puzzle / Mind Reader / This or That / Name Secret) ke shared canvas helpers --}}
<script>
window.CanvasKit = window.CanvasKit || (function () {
    const STACK = '"Noto Sans Devanagari", "Noto Sans Gujarati", sans-serif';
    const font = (size, weight = 'bold') => `${weight} ${size}px ${STACK}`;

    // Google Fonts unicode-range subsets me aate hain — sample text dena zaroori hai,
    // warna sirf latin subset load hota hai aur canvas fallback font me ban jaata hai.
    let fontsReady = null;
    function ensureFonts() {
        if (!fontsReady) {
            fontsReady = (async () => {
                for (const f of [
                    '400 48px "Noto Sans Devanagari"', '700 48px "Noto Sans Devanagari"',
                    '400 48px "Noto Sans Gujarati"', '700 48px "Noto Sans Gujarati"',
                ]) { try { await document.fonts.load(f, 'अआक કખગ 0123'); } catch (e) {} }
                await document.fonts.ready;
            })();
        }
        return fontsReady;
    }

    // Har draw se pehle — same offscreen canvas kai cards ke liye reuse hota hai,
    // pichhle card ka path/shadow/align agle card me leak na ho.
    function reset(ctx) {
        ctx.setTransform(1, 0, 0, 1, 0, 0);
        ctx.globalAlpha = 1;
        ctx.shadowBlur = 0;
        ctx.shadowColor = 'transparent';
        ctx.lineWidth = 1;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.beginPath();
    }

    function wrapLines(ctx, text, maxW) {
        const words = String(text ?? '').trim().split(/\s+/).filter(Boolean);
        const lines = [];
        let line = '';
        for (const w of words) {
            const test = line ? line + ' ' + w : w;
            if (line && ctx.measureText(test).width > maxW) {
                lines.push(line);
                line = w;
            } else {
                line = test;
            }
        }
        if (line) lines.push(line);
        return lines;
    }

    /** Text ko maxW × maxH box me fit karo: font chhota karte jao; minSize par bhi na aaye to aakhri line "…" */
    function fit(ctx, text, { maxW, maxH, size, minSize = 24, lineHeight = 1.3, weight = 'bold' }) {
        let s = size, lines;
        for (;;) {
            ctx.font = font(s, weight);
            lines = wrapLines(ctx, text, maxW);
            if (lines.length * s * lineHeight <= maxH || s <= minSize) break;
            s = Math.max(minSize, s - 2);
        }
        const lh = s * lineHeight;
        const maxLines = Math.max(1, Math.floor(maxH / lh));
        if (lines.length > maxLines) {
            lines = lines.slice(0, maxLines);
            let last = lines[maxLines - 1];
            while (last && ctx.measureText(last + '…').width > maxW) last = last.slice(0, -1);
            lines[maxLines - 1] = last.trimEnd() + '…';
        }
        return { lines, size: s, lh };
    }

    /** Lines ko (x, cy) ke around vertically center karke likho. */
    function drawLines(ctx, lines, x, cy, lh) {
        const prev = ctx.textBaseline;
        ctx.textBaseline = 'middle';
        const y0 = cy - ((lines.length - 1) * lh) / 2;
        lines.forEach((l, i) => ctx.fillText(l, x, y0 + i * lh));
        ctx.textBaseline = prev;
    }

    /** fit + drawLines ek call me. */
    function fitDraw(ctx, text, x, cy, opts) {
        const r = fit(ctx, text, opts);
        drawLines(ctx, r.lines, x, cy, r.lh);
        return r;
    }

    /** Ek line ka text maxW se bada ho to font chhota karo (minSize tak). ctx.font set ho jaata hai. */
    function fitOneLine(ctx, text, maxW, size, minSize = 24, weight = 'bold') {
        let s = size;
        ctx.font = font(s, weight);
        while (s > minSize && ctx.measureText(String(text ?? '')).width > maxW) {
            s -= 2;
            ctx.font = font(s, weight);
        }
        return s;
    }

    /** AI text ko innerHTML me daalne se pehle escape. */
    function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    /** AI kabhi list (["#a","#b"]) bhejta hai, kabhi text — server ko hamesha text. */
    function str(v) {
        return Array.isArray(v) ? v.join(' ') : (v == null ? '' : String(v));
    }

    /** Save ka error — Laravel validation (422) `message` deta hai, hamara code `error`. */
    function saveError(d) {
        const first = d && d.errors ? Object.values(d.errors)[0] : null;
        return (d && d.error) || (first && first[0]) || (d && d.message) || 'Failed to save';
    }

    return { STACK, font, ensureFonts, reset, wrapLines, fit, drawLines, fitDraw, fitOneLine, esc, str, saveError };
})();
</script>
