// رسم بارکد EAN-13 به صورت SVG (بدون وابستگی خارجی)

const L = ['0001101', '0011001', '0010011', '0111101', '0100011', '0110001', '0101111', '0111011', '0110111', '0001011'];
const G = ['0100111', '0110011', '0011011', '0100001', '0011101', '0111001', '0000101', '0010001', '0001001', '0010111'];
const R = ['1110010', '1100110', '1101100', '1000010', '1011100', '1001110', '1010000', '1000100', '1001000', '1110100'];
const PARITY = ['LLLLLL', 'LLGLGG', 'LLGGLG', 'LLGGGL', 'LGLLGG', 'LGGLLG', 'LGGGLL', 'LGLGLG', 'LGLGGL', 'LGGLGL'];

export function isValidEan13(code) {
    if (!/^\d{13}$/.test(code || '')) return false;
    let sum = 0;
    for (let i = 0; i < 12; i++) {
        sum += Number(code[i]) * (i % 2 === 0 ? 1 : 3);
    }
    return (10 - (sum % 10)) % 10 === Number(code[12]);
}

// الگوی باینری ۹۵ بیتی
export function ean13Pattern(code) {
    const digits = code.split('').map(Number);
    const parity = PARITY[digits[0]];
    let bits = '101';
    for (let i = 1; i <= 6; i++) {
        bits += parity[i - 1] === 'L' ? L[digits[i]] : G[digits[i]];
    }
    bits += '01010';
    for (let i = 7; i <= 12; i++) {
        bits += R[digits[i]];
    }
    bits += '101';
    return bits;
}

/**
 * SVG بارکد. اگر کد معتبر نباشد null برمی‌گرداند.
 * @returns {string|null}
 */
export function ean13Svg(code, { moduleWidth = 1.6, barHeight = 60, fontSize = 11 } = {}) {
    if (!isValidEan13(code)) return null;

    const bits = ean13Pattern(code);
    const quiet = 9; // حاشیه‌ی سفید دو طرف (به تعداد ماژول)
    const width = (bits.length + quiet * 2) * moduleWidth;
    const textHeight = fontSize + 4;
    const height = barHeight + textHeight;
    let rects = '';

    for (let i = 0; i < bits.length; i++) {
        if (bits[i] !== '1') continue;
        // نوارهای راهنما (guard) کمی بلندترند
        const guard = i < 3 || (i >= 45 && i < 50) || i >= 92;
        const h = guard ? barHeight + 5 : barHeight;
        const x = (quiet + i) * moduleWidth;
        rects += `<rect x="${x.toFixed(2)}" y="0" width="${moduleWidth.toFixed(2)}" height="${h}" fill="#000"/>`;
    }

    const cx = (x) => ((quiet + x) * moduleWidth).toFixed(2);
    const text = `
        <text x="${cx(-2)}" y="${barHeight + fontSize}" font-size="${fontSize}" font-family="monospace">${code[0]}</text>
        <text x="${cx(14)}" y="${barHeight + fontSize}" font-size="${fontSize}" font-family="monospace" text-anchor="middle" letter-spacing="2">${code.slice(1, 7)}</text>
        <text x="${cx(50)}" y="${barHeight + fontSize}" font-size="${fontSize}" font-family="monospace" text-anchor="middle" letter-spacing="2">${code.slice(7)}</text>`;

    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${width.toFixed(2)} ${height}" width="${width.toFixed(2)}" height="${height}" role="img" aria-label="barcode ${code}"><rect width="100%" height="100%" fill="#fff"/>${rects}${text}</svg>`;
}
