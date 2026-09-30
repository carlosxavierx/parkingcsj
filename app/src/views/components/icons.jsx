const base = {
  width: 18,
  height: 18,
  viewBox: '0 0 24 24',
  fill: 'none',
  stroke: 'currentColor',
  strokeWidth: 1.9,
  strokeLinecap: 'round',
  strokeLinejoin: 'round',
  'aria-hidden': true,
};

export const IconPin = (props) => (
  <svg {...base} {...props}>
    <path d="M12 21s7-5.5 7-11a7 7 0 1 0-14 0c0 5.5 7 11 7 11z" />
    <circle cx="12" cy="10" r="2.6" />
  </svg>
);

export const IconClock = (props) => (
  <svg {...base} {...props}>
    <circle cx="12" cy="12" r="9" />
    <path d="M12 7.5V12l3 1.8" />
  </svg>
);

export const IconBolt = (props) => (
  <svg {...base} {...props}>
    <path d="M13 2 4.5 13.5H11L10 22l8.5-11.5H12L13 2z" />
  </svg>
);

export const IconShield = (props) => (
  <svg {...base} {...props}>
    <path d="M12 3l7 3v5.5c0 4.4-3 8.1-7 9.5-4-1.4-7-5.1-7-9.5V6l7-3z" />
    <path d="m9 12 2 2 4-4" />
  </svg>
);

export const IconCar = (props) => (
  <svg {...base} {...props}>
    <path d="M4 16v2.5M20 16v2.5M3.5 16h17l-1.2-5.2A2.5 2.5 0 0 0 16.8 9H7.2a2.5 2.5 0 0 0-2.45 1.8L3.5 16z" />
    <path d="M6.5 13.5h.01M17.5 13.5h.01" />
  </svg>
);

export const IconNav = (props) => (
  <svg {...base} {...props}>
    <path d="M3.5 11 20.5 4l-7 17-2.3-7.7L3.5 11z" />
  </svg>
);

export const IconCheck = (props) => (
  <svg {...base} {...props}>
    <path d="m5 12.5 4.5 4.5L19 7.5" />
  </svg>
);

export const IconClose = (props) => (
  <svg {...base} {...props}>
    <path d="M6 6l12 12M18 6 6 18" />
  </svg>
);

export const IconCalendar = (props) => (
  <svg {...base} {...props}>
    <rect x="3.5" y="5" width="17" height="15.5" rx="2.5" />
    <path d="M3.5 10h17M8 3.5v3M16 3.5v3" />
  </svg>
);

export const IconPhone = (props) => (
  <svg {...base} {...props}>
    <path d="M7 3.5h3l1.5 4-2 1.5a12 12 0 0 0 5.5 5.5l1.5-2 4 1.5v3a2 2 0 0 1-2.2 2A16.5 16.5 0 0 1 5.5 5.7 2 2 0 0 1 7 3.5z" />
  </svg>
);

export const IconGlobe = (props) => (
  <svg {...base} {...props}>
    <circle cx="12" cy="12" r="9" />
    <path d="M3 12h18M12 3c2.5 2.6 2.5 15.4 0 18-2.5-2.6-2.5-15.4 0-18z" />
  </svg>
);

export const IconStar = (props) => (
  <svg {...base} fill="currentColor" stroke="none" {...props}>
    <path d="m12 3.5 2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.9l-5.2 2.7 1-5.8L3.5 9.7l5.9-.9L12 3.5z" />
  </svg>
);

export const IconWheelchair = (props) => (
  <svg {...base} {...props}>
    <circle cx="11" cy="4.5" r="2" />
    <path d="M11 8v5h5l3 6M11 13a5 5 0 1 0 4.5 7" />
  </svg>
);

export const IconSparkles = (props) => (
  <svg {...base} {...props}>
    <path d="M12 3.5 13.6 8 18 9.6 13.6 11.2 12 15.7 10.4 11.2 6 9.6 10.4 8 12 3.5z" />
    <path d="M18.5 15.5l.8 2 2 .8-2 .8-.8 2-.8-2-2-.8 2-.8.8-2z" />
  </svg>
);

export const IconArrow = (props) => (
  <svg {...base} {...props}>
    <path d="M5 12h14M13 6l6 6-6 6" />
  </svg>
);

export const IconMap = (props) => (
  <svg {...base} {...props}>
    <path d="m9 4.5 6 2.5 5-2v12.5l-5 2-6-2.5-5 2V6.5l5-2z" />
    <path d="M9 4.5v12.5M15 7v12.5" />
  </svg>
);
