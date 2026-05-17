/**
 * MkElement — shared base class for all Markommerce custom elements.
 *
 * CLS-prevention contract:
 * The default render() returns Lit's `nothing` sentinel. LitElement.update()
 * calls render() and writes the result into the render root via lit-html render().
 * LitElement's createRenderRoot() override sets renderOptions.renderBefore to
 * renderRoot.firstChild so the ChildPart is positioned *before* any existing
 * light-DOM children. Returning `nothing` produces an empty ChildPart range,
 * leaving the existing children intact. Verified in
 * node_modules/lit-element/development/lit-element.js line 110
 * (`renderBefore ??= renderRoot.firstChild`) and
 * node_modules/lit-html/development/lit-html.js line 1492 (the ChildPart's
 * `endNode` becomes the first existing child, so cleared content only covers
 * the empty range between the inserted Comment marker and that endNode).
 *
 * TypeScript return-type note:
 * The base render() is declared as `override render(): unknown` to match
 * LitElement.render(): unknown. This allows subclasses to override with a
 * narrower return type such as TemplateResult without TypeScript errors.
 * Declaring it as `typeof nothing` would prevent subclasses from returning
 * TemplateResult.
 */
import { LitElement, nothing } from 'lit';

export class MkElement extends LitElement {
  override createRenderRoot(): HTMLElement {
    this.renderOptions.renderBefore ??= this.firstChild;
    return this;
  }

  override render(): unknown {
    return nothing;
  }
}
