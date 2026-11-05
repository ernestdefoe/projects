// @ts-ignore: an optional dependency, without typings here.
import Gallery from 'ext:fof/photoswipe/forum/extenders/Gallery';

declare const flarum: any;

// With FoF PhotoSwipe, a project's pictures open in its lightbox like a post's.
export default [
  ...('fof-photoswipe' in flarum.extensions ? [new Gallery('ext:ernestdefoe/projects/forum/components/ProjectPage', '.ProjectPage-content')] : []),
];
