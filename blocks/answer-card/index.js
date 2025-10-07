import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import Edit from './edit';
import save from './save';
import metadata from './block.json';

registerBlockType(metadata.name, {
    edit: Edit,
    save,
    title: __('Answer Card', 'geo-ai'),
    description: __(
        'A concise TL;DR summary with key facts optimized for AI answer engines.',
        'geo-ai'
    ),
    icon: 'info',
});
