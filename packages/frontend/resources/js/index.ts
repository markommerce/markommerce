// Kernel entry — populated by tasks 007-009

export {
  addMixin,
  defineAllComponents,
  getMixinChain,
  getRegisteredComponents,
  registerBase,
  RegistryError,
} from './registry';
export type { Constructor, Mixin, MixinDescriptor, RegisteredComponent } from './registry';

export * from './events';
export { registerHook, runHook, Hooks } from './hooks';
export type { HookRegistry, HookHandler } from './hooks';
