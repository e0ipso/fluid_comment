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
        getResponseDocument(entryPointUrl).then(responseDoc => {
            const nodeUrl = getDeepProp(responseDoc, `links.${this.props.hostType}.href`) + `/${this.props.hostId}`;
            const userId = getDeepProp(responseDoc, 'meta.links.me.meta.id');
            const loggedIn = userId !== false;
            this.setState({ loggedIn, userId });
            getResponseDocument(nodeUrl).then(currentNode => {
                this.setState({currentNode: getDeepProp(currentNode, 'data')});
            });
        });
    }

    render() {
        const { currentNode, loggedIn, userId } = this.state;
        const { hostId, commentType } = this.props;

        return (currentNode &&
          <FluidCommentWrapper
            loginUrl={loggedIn === false ? loginUrl : null}
            currentUserId={userId}
            commentType={commentType}
            currentNode={currentNode}
            hostId={hostId}
          />
        );
    }
}

export default FluidCommentWidget;
