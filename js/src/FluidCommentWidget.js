'use strict';

import React from 'react';
import FluidCommentWrapper from './FluidCommentWrapper';
import { getDeepProp, getResponseDocument, getUrl } from "./functions";

const entryPointUrl = getUrl('/jsonapi');

class FluidCommentWidget extends React.Component {

    constructor(props) {
        super(props);
        this.state = { currentNode: null };
    }

    componentDidMount() {
        const { commentedResourceUrl } = this.props;
        getResponseDocument(entryPointUrl).then(responseDoc => {
            getResponseDocument(commentedResourceUrl).then(currentNode => {
                this.setState({currentNode: getDeepProp(currentNode, 'data')});
            });
        });
    }

    render() {
        const { currentNode } = this.state;
        const { commentType } = this.props;

        return (
          <FluidCommentWrapper
            commentType={commentType}
            currentNode={currentNode}
          />
        );
    }
}

export default FluidCommentWidget;
