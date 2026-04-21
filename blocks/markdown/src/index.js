import { registerBlockType } from '@wordpress/blocks';
import { PlainText, useBlockProps } from '@wordpress/block-editor';
import { RawHTML } from '@wordpress/element';
import { useServerSideRender } from '@wordpress/server-side-render';
import metadata from '../block.json';

/**
 * Render a live server-side preview when the block is not selected.
 *
 * @param {{attributes: Record<string, unknown>}} props Block props.
 * @return {JSX.Element}
 */
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
	/**
	 * Render the editable markdown field in the block editor.
	 *
	 * @param {{attributes: Record<string, unknown>, setAttributes: Function, isSelected: boolean}} props Block edit props.
	 * @return {JSX.Element}
	 */
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
	/**
	 * Save raw markdown so frontend rendering can be controlled by the dynamic block callback.
	 *
	 * @param {{attributes: Record<string, unknown>}} props Block save props.
	 * @return {JSX.Element}
	 */
	save: function SaveMarkdownBlock( { attributes } ) {
		return <RawHTML>{ attributes.content || '' }</RawHTML>;
	},
} );
