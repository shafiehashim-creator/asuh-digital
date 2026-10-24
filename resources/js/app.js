// Let Vite fingerprint the brand images so Blade can use Vite::asset().
// Exported so the bundler does not tree-shake the glob away.
export const images = import.meta.glob(['../images/**']);
