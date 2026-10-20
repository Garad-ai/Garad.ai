export function BrandIcon({ k, size = 40 }: { k: string; size?: number }) {
  const common = { width: size, height: size, viewBox: "0 0 40 40", "aria-hidden": true } as const
  const box = (fill: string) => <rect width="40" height="40" rx="9" fill={fill} />
  if (k === "facebook")
    return <svg {...common}>{box("#1877f2")}<path fill="#fff" d="M22.2 32V21.2h3.6l.6-4.2h-4.2v-2.7c0-1.2.4-2 2.1-2h2.2V8.6c-.4 0-1.7-.2-3.2-.2-3.2 0-5.3 1.9-5.3 5.5V17h-3.6v4.2h3.6V32z" /></svg>
  if (k === "instagram")
    return (
      <svg {...common}>
        <defs><linearGradient id="smxig" x1="0" y1="1" x2="1" y2="0"><stop offset="0" stopColor="#feda75" /><stop offset=".35" stopColor="#fa7e1e" /><stop offset=".6" stopColor="#d62976" /><stop offset="1" stopColor="#4f5bd5" /></linearGradient></defs>
        {box("url(#smxig)")}
        <rect x="9.5" y="9.5" width="21" height="21" rx="6" fill="none" stroke="#fff" strokeWidth="2.2" />
        <circle cx="20" cy="20" r="5" fill="none" stroke="#fff" strokeWidth="2.2" />
        <circle cx="26.4" cy="13.6" r="1.4" fill="#fff" />
      </svg>
    )
  if (k === "x")
    return <svg {...common}>{box("#000")}<path fill="#fff" d="M22.6 18.2 29.4 10h-1.6l-5.9 6.9L17.2 10H11.8l7.1 10.4L11.8 29h1.6l6.2-7.3 5 7.3H30zm-2.2 2.6-.7-1-5.7-8.1h2.5l4.6 6.6.7 1 6 8.5h-2.5z" /></svg>
  if (k === "linkedin")
    return <svg {...common}>{box("#2867b2")}<path fill="#fff" d="M11.5 16.5h3.6V28h-3.6zm1.8-5.7a2.1 2.1 0 1 1 0 4.2 2.1 2.1 0 0 1 0-4.2m4.2 5.7H21v1.6c.5-.9 1.7-1.9 3.5-1.9 3.700 0 4.400 2.400 4.400 5.600V28h-3.600v-5.400c0-1.300 0-3-1.800-3s-2.100 1.400-2.100 2.900V28h-3.600z" /></svg>
  if (k === "tiktok")
    return <svg {...common}>{box("#000")}<path fill="#fff" d="M23.3 10c.3 2.600 1.800 4.200 4.400 4.400v3.200c-1.500.1-2.800-.4-4.300-1.300v5.900c0 7.500-8.200 9.800-11.500 4.400-2.100-3.500-.8-9.600 5.600-9.800v3.400c-.5.100-1 .2-1.400.4-1.300.4-2.100 1.700-1.700 3.100.7 2.900 5.700 2.400 5.300-1.500V10z" /></svg>
  if (k === "youtube" || k === "youtube-shorts")
    return <svg {...common}>{box("#f00")}<path fill="#fff" d="M30.600 14.200a2.600 2.600 0 0 0-1.800-1.800C27.200 12 20 12 20 12s-7.200 0-8.800.4a2.600 2.600 0 0 0-1.800 1.800C9 15.800 9 20 9 20s0 4.200.4 5.800a2.600 2.600 0 0 0 1.800 1.800c1.600.4 8.800.4 8.800.4s7.200 0 8.800-.4a2.600 2.600 0 0 0 1.800-1.800c.4-1.600.4-5.800.4-5.800s0-4.200-.4-5.800M17.800 23.200v-6.400l5.600 3.200z" /></svg>
  return <svg {...common}>{box("#6b7280")}</svg>
}
