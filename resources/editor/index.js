roots.register.blocks(`./`);
roots.register.plugins(`./`);

if (import.meta.webpackHot) {
  import.meta.webpackHot.accept(console.error);
}
