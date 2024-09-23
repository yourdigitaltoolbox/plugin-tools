/**
 * @param {import('@roots/bud').Bud} bud
 */
export default (bud) => {
  bud
    .setPath(`@src`, `resources`)
    .alias(`@editor`, bud.path(`@src`, `editor`))
    .entry(`editor`, `@editor/index.js`)
    .minimize(bud.isProduction);
};
