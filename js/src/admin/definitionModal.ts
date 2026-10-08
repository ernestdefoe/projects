import type { IInternalModalAttrs } from 'flarum/common/components/Modal';
import type { CategoryDef } from '../common/api';

/** What ProjectsConfigManager passes to each category, field and button modal. */
export interface DefinitionModalAttrs<T> extends IInternalModalAttrs {
  item?: T;
  categories: CategoryDef[];
  badges: { id: number; name: string }[];
  onsave?: () => void;
}
