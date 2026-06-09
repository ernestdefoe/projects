import Extend from 'flarum/common/extenders';
import app from 'flarum/admin/app';
import ProjectsConfigManager from './components/ProjectsConfigManager';

declare const m: any;
const t = (k: string) => app.translator.trans('ernestdefoe-projects.admin.' + k);

export default [
  new Extend.Admin()
    .setting(() => ({
      setting: 'ernestdefoe-projects.excerpt_limit',
      type: 'number',
      label: t('settings.excerpt_limit'),
      help: t('settings.excerpt_limit_help'),
      default: 280,
    }))
    .setting(() => ({
      setting: 'ernestdefoe-projects.allow_adhoc_links',
      type: 'boolean',
      label: t('settings.allow_adhoc_links'),
      help: t('settings.allow_adhoc_links_help'),
      default: false,
    }))
    .setting(() => ({
      setting: 'ernestdefoe-projects.publish_badge_id',
      type: 'number',
      label: t('settings.publish_badge_id'),
      help: t('settings.publish_badge_id_help'),
    }))
    .permission(() => ({ icon: 'fas fa-plus', label: t('permissions.create'), permission: 'projects.create' }), 'start')
    .permission(() => ({ icon: 'fas fa-bolt', label: t('permissions.skip_moderation'), permission: 'projects.skipModeration' }), 'start')
    .permission(() => ({ icon: 'fas fa-gavel', label: t('permissions.moderate'), permission: 'projects.moderate' }), 'moderate')
    // Category / custom-field / button-slot managers, on the same settings page.
    .customSetting(() => m(ProjectsConfigManager), -10),
];
