import { useBlockProps, RichText } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

export default function save({ attributes }) {
    const { tldr, keyFacts } = attributes;

    return (
        <div {...useBlockProps.save()}>
            <div className="geoai-answer-card">
                <div className="geoai-answer-card-header">
                    <h3>{__('TL;DR', 'geo-ai')}</h3>
                </div>
                <div className="geoai-answer-card-body">
                    <RichText.Content
                        tagName="p"
                        value={tldr}
                        className="geoai-answer-card-tldr"
                    />

                    {keyFacts && keyFacts.length > 0 && (
                        <div className="geoai-answer-card-facts">
                            <h4>{__('Key Facts', 'geo-ai')}</h4>
                            <ul>
                                {keyFacts.map((fact, index) => (
                                    <li key={index}>
                                        <RichText.Content
                                            tagName="span"
                                            value={fact}
                                        />
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}
