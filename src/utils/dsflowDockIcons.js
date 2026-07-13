import { h } from 'vue'
import { iconFor } from './landingIcons'

const path = (d, extra = {}) => ['path', { d, ...extra }]
const rect = (x, y, width, height, rx = 2, extra = {}) => [
  'rect',
  { x, y, width, height, rx, ...extra },
]
const circle = (cx, cy, r, extra = {}) => ['circle', { cx, cy, r, ...extra }]
const line = (x1, y1, x2, y2, extra = {}) => ['line', { x1, y1, x2, y2, ...extra }]

function createDockIcon(name, nodes) {
  return {
    name: `DsflowDock${name}`,
    inheritAttrs: false,
    props: {
      size: { type: [Number, String], default: 24 },
      strokeWidth: { type: [Number, String], default: 2 },
      color: { type: String, default: 'currentColor' },
    },
    setup(props, { attrs }) {
      return () => h(
        'svg',
        {
          ...attrs,
          xmlns: 'http://www.w3.org/2000/svg',
          width: props.size,
          height: props.size,
          viewBox: '0 0 24 24',
          fill: 'none',
          stroke: props.color,
          'stroke-width': props.strokeWidth,
          'stroke-linecap': 'round',
          'stroke-linejoin': 'round',
          'aria-hidden': 'true',
          focusable: 'false',
          class: ['dsf-dock-icon', `dsf-dock-icon--${name.toLowerCase()}`, attrs.class],
          'data-dsf-icon': `dsflow-${name.toLowerCase()}`,
        },
        nodes.map(([tag, attributes], index) => h(tag, { ...attributes, key: index }))
      )
    },
  }
}

const DOCK_ICONS = {
  'dsflow-why': createDockIcon('Why', [
    path('M3.5 12s3.2-5 8.5-5 8.5 5 8.5 5-3.2 5-8.5 5-8.5-5-8.5-5Z'),
    circle(12, 12, 2.25),
  ]),
  'dsflow-blocks': createDockIcon('Blocks', [
    rect(4, 4, 7, 7, 1.25),
    rect(13, 4, 7, 7, 1.25),
    rect(4, 13, 7, 7, 1.25),
    rect(13, 13, 7, 7, 1.25),
  ]),
  'dsflow-ready': createDockIcon('Ready', [
    rect(4, 5, 16, 14, 2),
    path('m6.5 17 4-4 3 3 2-2 2.5 3'),
  ]),
  'dsflow-editor': createDockIcon('Editor', [
    rect(3.5, 4, 17, 16, 2),
    line(3.5, 8.5, 20.5, 8.5),
    path('m9 10 8 4-3.4 1.2 1.8 3.4-2 1-1.7-3.4L9 19Z'),
  ]),
  'dsflow-theme': createDockIcon('Theme', [
    path('M4 7h4m4 0h8'),
    circle(10, 7, 2),
    path('M4 12h9m4 0h3'),
    circle(15, 12, 2),
    path('M4 17h5m4 0h7'),
    circle(11, 17, 2),
  ]),
  'dsflow-commerce': createDockIcon('Commerce', [
    rect(4, 8, 16, 12, 2),
    path('M9 10V7a3 3 0 0 1 6 0v3'),
  ]),
  'dsflow-layouts': createDockIcon('Layouts', [
    rect(4, 4, 16, 16, 2),
    line(4, 8.5, 20, 8.5),
    line(4, 15.5, 20, 15.5),
  ]),
  'dsflow-campaigns': createDockIcon('Campaigns', [
    path('M4 10v4h4l8 4V6l-8 4Z'),
    path('M7 14 8.5 19H11'),
    path('m19 8 2-2m-2 6h2m-2 4 2 2'),
  ]),
  'dsflow-engagement': createDockIcon('Engagement', [
    rect(5, 4, 14, 16, 2),
    path('M8 8h8M8 12h5'),
    path('m9 16 2 2 4-4'),
  ]),
  'dsflow-seo': createDockIcon('Seo', [
    circle(10, 10, 5.5),
    line(14.2, 14.2, 20, 20),
    path('m7.2 11.7 2.2-2.2 1.8 1.7 2.4-3'),
  ]),
  'dsflow-security': createDockIcon('Security', [
    path('m12 3.5 7 3v5c0 4-2.4 6.9-7 9-4.6-2.1-7-5-7-9v-5Z'),
    path('m8.5 12 2.2 2.2 4.8-5'),
  ]),
  'dsflow-agencies': createDockIcon('Agencies', [
    rect(4, 7, 16, 12, 2),
    path('M9 7V5h6v2'),
    line(4, 12, 20, 12),
  ]),
  'dsflow-workflow': createDockIcon('Workflow', [
    rect(9, 3.5, 6, 5, 1),
    rect(3.5, 15.5, 6, 5, 1),
    rect(14.5, 15.5, 6, 5, 1),
    path('M12 8.5V12M6.5 15.5V12h11v3.5'),
  ]),
  'dsflow-redirects': createDockIcon('Redirects', [
    path('M4 6h7a7 7 0 0 1 7 7v5'),
    path('m15 15 3 3 3-3'),
  ]),
  'dsflow-mail': createDockIcon('Mail', [
    path('M20 4 3.5 10.8l7 2.5L13 20Z'),
    line(10.5, 13.3, 20, 4),
  ]),
  'dsflow-launch': createDockIcon('Launch', [
    path('M12 4v10m-4-4 4 4 4-4'),
    path('M5 17v3h14v-3'),
  ]),
}

export const DSFLOW_DOCK_ICON_NAMES = Object.keys(DOCK_ICONS)

export function dockIconFor(name) {
  return DOCK_ICONS[name] || iconFor(name)
}
