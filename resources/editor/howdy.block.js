import { __ } from "@wordpress/i18n";

/* Block name */
export const name = `clover/howdy`;

/* Block title */
export const title = __(`Howdy`, `clover`);

/* Block category */
export const category = `text`;

/* Block edit */
export const edit = () => <></>;

/* Block save */
export const save = () => <></>;

/* Block styles */
export const styles = [
  { name: `default`, label: `Default`, isDefault: true },
  { name: `custom`, label: `Custom` },
];
