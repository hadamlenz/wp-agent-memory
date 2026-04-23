import { registerBlockBindingsSource } from '@wordpress/blocks';
import { store as coreDataStore } from '@wordpress/core-data';

registerBlockBindingsSource( {
	name: 'wpam/entry-stats',
	label: 'Memory Entry Stats',
	usesContext: [ 'postId', 'postType' ],

	getValues( { bindings, context, select } ) {
		const values = {};
		const postId = context?.postId;

		if ( ! postId ) {
			return values;
		}

		const record = select( coreDataStore ).getEntityRecord(
			'postType',
			'memory_entry',
			postId
		);

		if ( ! record?.meta ) {
			return values;
		}

		const allowed = [ 'useful_count', 'usage_count', 'last_used_gmt' ];

		Object.entries( bindings ).forEach( ( [ attr, props ] ) => {
			const key = props?.args?.key;
			if ( key && allowed.includes( key ) ) {
				values[ attr ] = record.meta[ key ] ?? '';
			}
		} );

		return values;
	},

	canUserEditValue() {
		return false;
	},

	getFieldsList() {
		return [
			{
				label: 'Useful Count',
				args: { key: 'useful_count' },
				default: '0',
				type: 'string',
			},
			{
				label: 'Usage Count',
				args: { key: 'usage_count' },
				default: '0',
				type: 'string',
			},
			{
				label: 'Last Used (GMT)',
				args: { key: 'last_used_gmt' },
				default: '',
				type: 'string',
			},
		];
	},
} );
