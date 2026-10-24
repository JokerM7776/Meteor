<?php
declare(strict_types=1);
namespace Core;

/** A small set of common emoji shortcodes for extended Markdown (:tada:). Unknown codes stay as text. */
final class Emoji
{
    public const CODES = [
        'smile' => '😄', 'smiley' => '😃', 'grin' => '😁', 'joy' => '😂', 'laughing' => '😆', 'wink' => '😉', 'blush' => '😊',
        'slightly_smiling_face' => '🙂', 'upside_down_face' => '🙃', 'heart_eyes' => '😍', 'kissing_heart' => '😘', 'yum' => '😋',
        'thinking' => '🤔', 'neutral_face' => '😐', 'expressionless' => '😑', 'unamused' => '😒', 'roll_eyes' => '🙄', 'smirk' => '😏',
        'sweat_smile' => '😅', 'relieved' => '😌', 'pensive' => '😔', 'confused' => '😕', 'worried' => '😟', 'cry' => '😢', 'sob' => '😭',
        'angry' => '😠', 'rage' => '😡', 'scream' => '😱', 'flushed' => '😳', 'sunglasses' => '😎', 'nerd_face' => '🤓', 'sleeping' => '😴',
        'mask' => '😷', 'partying_face' => '🥳', 'hugs' => '🤗', 'shushing_face' => '🤫', 'see_no_evil' => '🙈', 'skull' => '💀',
        'heart' => '❤️', 'orange_heart' => '🧡', 'yellow_heart' => '💛', 'green_heart' => '💚', 'blue_heart' => '💙', 'purple_heart' => '💜',
        'broken_heart' => '💔', 'sparkling_heart' => '💖', '+1' => '👍', 'thumbsup' => '👍', '-1' => '👎', 'thumbsdown' => '👎',
        'clap' => '👏', 'wave' => '👋', 'pray' => '🙏', 'muscle' => '💪', 'ok_hand' => '👌', 'raised_hands' => '🙌', 'v' => '✌️',
        'point_up' => '☝️', 'point_right' => '👉', 'point_left' => '👈', 'point_down' => '👇', 'eyes' => '👀', 'brain' => '🧠',
        'tada' => '🎉', 'sparkles' => '✨', 'fire' => '🔥', 'star' => '⭐', 'star2' => '🌟', 'stars' => '🌠', 'comet' => '☄️',
        'zap' => '⚡', 'boom' => '💥', '100' => '💯', 'rocket' => '🚀', 'warning' => '⚠️', 'white_check_mark' => '✅',
        'heavy_check_mark' => '✔️', 'x' => '❌', 'no_entry' => '⛔', 'construction' => '🚧', 'question' => '❓', 'exclamation' => '❗',
        'information_source' => 'ℹ️', 'new' => '🆕', 'bulb' => '💡', 'memo' => '📝', 'pencil2' => '✏️', 'book' => '📖', 'books' => '📚',
        'link' => '🔗', 'lock' => '🔒', 'unlock' => '🔓', 'key' => '🔑', 'bell' => '🔔', 'mag' => '🔍', 'gear' => '⚙️', 'wrench' => '🔧',
        'hammer' => '🔨', 'bug' => '🐛', 'computer' => '💻', 'iphone' => '📱', 'email' => '📧', 'calendar' => '📅', 'clock' => '🕐',
        'hourglass' => '⌛', 'chart_with_upwards_trend' => '📈', 'package' => '📦', 'pushpin' => '📌', 'speech_balloon' => '💬',
        'coffee' => '☕', 'tea' => '🍵', 'beer' => '🍺', 'pizza' => '🍕', 'cake' => '🍰', 'apple' => '🍎', 'gift' => '🎁',
        'trophy' => '🏆', 'medal' => '🏅', 'art' => '🎨', 'musical_note' => '🎵', 'camera' => '📷', 'airplane' => '✈️', 'car' => '🚗',
        'sunny' => '☀️', 'cloud' => '☁️', 'umbrella' => '☔', 'snowflake' => '❄️', 'rainbow' => '🌈', 'crescent_moon' => '🌙',
        'earth_africa' => '🌍', 'earth_americas' => '🌎', 'earth_asia' => '🌏', 'globe_with_meridians' => '🌐', 'seedling' => '🌱',
        'evergreen_tree' => '🌲', 'rose' => '🌹', 'sunflower' => '🌻', 'cat' => '🐱', 'dog' => '🐶', 'arrow_right' => '➡️',
        'arrow_left' => '⬅️', 'arrow_up' => '⬆️', 'arrow_down' => '⬇️',
    ];
}
