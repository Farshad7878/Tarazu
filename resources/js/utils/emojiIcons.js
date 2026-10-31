// Maps the built-in glyphs used in app messages and labels to lucide icon components.
// Emoji that users enter themselves (e.g. a category's custom icon) are NOT in this map,
// so they keep rendering as plain text.
import {
    X,
    CircleCheck,
    WifiOff,
    ShoppingCart,
    Trash2,
    User,
    TriangleAlert,
    Pause,
    Play,
    Package,
    Tag,
} from 'lucide-vue-next';

const ICONS = {
    '❌': X,
    '✅': CircleCheck,
    '📴': WifiOff,
    '🛒': ShoppingCart,
    '🗑': Trash2,
    '👤': User,
    '⚠': TriangleAlert,
    '⏸': Pause,
    '▶': Play,
    '📦': Package,
    '🏷': Tag,
};

/** Returns the icon component for a built-in glyph, or null when the value is not one of them. */
export function emojiIcon(value) {
    if (!value) return null;
    return ICONS[String(value).replace(/\uFE0F/g, '')] || null;
}
