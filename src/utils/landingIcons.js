import {
  Sparkles, ShieldCheck, LockKeyhole, Fingerprint, Code2, FileCode2, FileSearch,
  Paintbrush2, Palette, Layers3, Layout, Columns, Grid3x3, Briefcase, Store, UsersRound,
  Mail, FormInput, BellRing, Megaphone, Clock3, CalendarDays, Search, Filter, Zap, Rocket,
  Check, Star, Heart, Globe2, Monitor, Smartphone, FileText, Settings, MousePointerClick,
  LayoutPanelTop, Wand2, Gauge, Boxes,
} from 'lucide-vue-next'

/**
 * Curated icon set so landing blocks can expose an editable icon through a plain
 * `select` field (value = kebab name). Render with `iconFor(name)`.
 */
const ICONS = {
  sparkles: Sparkles,
  'shield-check': ShieldCheck,
  lock: LockKeyhole,
  fingerprint: Fingerprint,
  code: Code2,
  'file-code': FileCode2,
  'file-search': FileSearch,
  paintbrush: Paintbrush2,
  palette: Palette,
  layers: Layers3,
  layout: Layout,
  columns: Columns,
  grid: Grid3x3,
  briefcase: Briefcase,
  store: Store,
  users: UsersRound,
  mail: Mail,
  'form-input': FormInput,
  bell: BellRing,
  megaphone: Megaphone,
  clock: Clock3,
  calendar: CalendarDays,
  search: Search,
  filter: Filter,
  zap: Zap,
  rocket: Rocket,
  check: Check,
  star: Star,
  heart: Heart,
  globe: Globe2,
  monitor: Monitor,
  smartphone: Smartphone,
  'file-text': FileText,
  settings: Settings,
  'mouse-pointer': MousePointerClick,
  'panel-top': LayoutPanelTop,
  wand: Wand2,
  gauge: Gauge,
  boxes: Boxes,
}

export const LANDING_ICON_NAMES = Object.keys(ICONS)

export function iconFor(name) {
  return ICONS[name] || Sparkles
}
