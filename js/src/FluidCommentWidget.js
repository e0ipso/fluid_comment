'use strict';

import React from 'react';
import FluidCommentWrapper from './FluidCommentWrapper';
import { getDeepProp, getResponseDocument, getUrl } from "./functions";
import { getRelUri, objectHasLinkWithRel } from "./routes";

const loginUrl = getUrl('/user/login?_format=json');
const entryPointUrl = getUrl('/jsonapi');

class FluidCommentWidget extends React.Component {

    constructor(props) {
        super(props);
        this.state = { loggedIn: null, currentNode: null };
    }

    componentDidMount() {
        const { commentedResourceUrl } = this.props;
        getResponseDocument(entryPointUrl).then(responseDoc => {
            const loggedIn = getDeepProp(responseDoc, 'meta.links.me.href') !== false;
            this.setState({loggedIn});
            getResponseDocument(commentedResourceUrl).then(currentNode => {
                this.setState({currentNode: getDeepProp(currentNode, 'data')});
            });
        });
    }

    render() {
        const { currentNode, loggedIn } = this.state;
        const { commentType } = this.props;
        const show = currentNode && objectHasLinkWithRel(currentNode, 'comments', getRelUri('collection'));
        return (show &&
          <FluidCommentWrapper
            loginUrl={loggedIn === false ? loginUrl : null}
            commentType={commentType}
            currentNode={currentNode}
          />
        );
    }
}

export default FluidCommentWidget;
