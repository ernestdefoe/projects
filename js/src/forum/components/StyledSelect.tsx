import Component from 'flarum/common/Component';
import Dropdown from 'flarum/common/components/Dropdown';
import Button from 'flarum/common/components/Button';

declare const m: any;

interface StyledSelectAttrs {
  value: string;
  options: Record<string, string>;
  onchange: (value: string) => void;
  disabled?: boolean;
}

/**
 * A select control whose dropdown LIST is Flarum-styled (built from Flarum's
 * Dropdown + Button), instead of a native <select> whose popup always falls
 * back to the browser's own chrome. Same {value, options, onchange} shape as
 * core's Select, so it's a drop-in replacement.
 */
export default class StyledSelect extends Component<StyledSelectAttrs> {
  view() {
    const { value, options, onchange, disabled } = this.attrs;
    const label = options[value] ?? Object.values(options)[0] ?? '';

    return m(
      Dropdown,
      {
        className: 'ProjectSelect Select',
        buttonClassName: 'FormControl ProjectSelect-toggle',
        menuClassName: 'ProjectSelect-menu',
        caretIcon: 'fas fa-chevron-down',
        disabled,
        label,
      },
      Object.entries(options).map(([val, text]) =>
        m(
          Button,
          {
            className: 'ProjectSelect-option',
            icon: String(val) === String(value) ? 'fas fa-check' : true,
            onclick: () => onchange(val),
          },
          text
        )
      )
    );
  }
}
