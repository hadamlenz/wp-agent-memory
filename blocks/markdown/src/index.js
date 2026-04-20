import { registerBlockType } from '@wordpress/blocks';
import { PlainText, useBlockProps } from '@wordpress/block-editor';
import { RawHTML } from '@wordpress/element';
import { useServerSideRender } from '@wordpress/server-side-render';
import metadata from '../block.json';

function MarkdownPreview( { attributes } ) {
	const blockProps = useBlockProps();
	const { content, status } = useServerSideRender( {
		block: 'wpam/markdown',
		attributes,
	} );

	return (
		<div { ...blockProps }>
			{ status === 'loading' && <p>Loading preview…</p> }
			{ content && <RawHTML>{ content }</RawHTML> }
		</div>
	);
}

registerBlockType( metadata, {
	edit: function EditMarkdownBlock( { attributes, setAttributes, isSelected } ) {
		const blockProps = useBlockProps();

		if ( ! isSelected ) {
			return <MarkdownPreview attributes={ attributes } />;
		}

		return (
			<div { ...blockProps }>
				<PlainText
					value={ attributes.content || '' }
					onChange={ ( value ) => setAttributes( { content: value } ) }
					placeholder="Write Markdown here…"
					style={ { fontFamily: 'monospace', minHeight: '6em', whiteSpace: 'pre-wrap' } }
				/>
			</div>
		);
	},
	save: function SaveMarkdownBlock( { attributes } ) {
		return <RawHTML>{ attributes.content || '' }</RawHTML>;
	},
} );
