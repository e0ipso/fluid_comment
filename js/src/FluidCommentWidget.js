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
            const loggedIn = getDeepProp(responseDoc, 'meta.links.me.href') !== false;
            this.setState({loggedIn});
            getResponseDocument(nodeUrl).then(currentNode => {
                this.setState({currentNode: getDeepProp(currentNode, 'data')});
            });
        });
    }

    render() {
        const show = this.state.currentNode && objectHasLinkWithRel(this.state.currentNode, 'comments', getRelUri('collection'));
        return <div>
            {show && <FluidCommentWrapper
                loginUrl={this.state.loggedIn === false ? loginUrl : null}
                commentType={this.props.commentType}
                currentNode={this.state.currentNode}
                hostId={this.props.hostId}
                threaded={this.props.threaded}
            />}
        </div>
    }
}

export default FluidCommentWidget;
